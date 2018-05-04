<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509;

use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateSignatureVerifier;
use ipl\Sql\Insert;
use ipl\Sql\Select;
use ipl\Sql\Update;


class CertificateUtils
{
    /**
     * Possible public key types
     *
     * @var string[]
     */
    protected static $pubkeyTypes = [
        -1                  => 'unknown',
        OPENSSL_KEYTYPE_RSA => 'RSA',
        OPENSSL_KEYTYPE_DSA => 'DSA',
        OPENSSL_KEYTYPE_DH  => 'DH',
        OPENSSL_KEYTYPE_EC  => 'EC'
    ];

    /**
     * Convert the given chunk from PEM to DER
     *
     * @param   string  $pem
     *
     * @return  string
     */
    private static function pem2der($pem)
    {
        $lines = explode("\n", $pem);

        $der = '';

        foreach ($lines as $line) {
            if (strpos($line, '-----') === 0) {
                continue;
            }

            $der .= base64_decode($line);
        }

        return $der;
    }

    /**
     * Convert the given chunk from DER to PEM
     *
     * @param   string  $der
     *
     * @return  string
     */
    private static function der2pem($der)
    {
        $block = chunk_split(base64_encode($der), 64, "\n");

        return "-----BEGIN CERTIFICATE-----\n{$block}-----END CERTIFICATE-----";
    }

    /**
     * Get the short name from the given DN
     *
     * If the given DN contains a CN, the CN is returned. Else, the DN is returned as string.
     *
     * @param   array   $dn
     *
     * @return  string  The CN if it exists or the full DN as string
     */
    private static function shortNameFromDN(array $dn)
    {
        if (isset($dn['CN'])) {
            return $dn['CN'];
        } else {
            $result = '';
            foreach ($dn as $key => $value) {
                if ($result != '') {
                    $result .= ', ';
                }
                $result .= "{$key}={$value}";
            }
            return $result;
        }
    }

    /**
     * Split the given Subject Alternative Names into key-value pairs
     *
     * @param   string  $sans
     *
     * @return  \Generator
     */
    private static function splitSANs($sans)
    {
        preg_match_all('/(?:^|, )([^:]+):/', $sans, $keys);
        $values = preg_split('/(^|, )[^:]+:\s*/', $sans);
        for ($i = 0; $i < count($keys[1]); $i++) {
            yield [$keys[1][$i], $values[$i + 1]];
        }
    }

    public static function findOrInsertCert($db, $cert) {
        $certInfo = openssl_x509_parse($cert);

        $fingerprint = openssl_x509_fingerprint($cert, 'sha256', true);

        $row = $db->select(
            (new Select())
                ->columns(['id'])
                ->from('x509_certificate')
                ->where(['fingerprint = ?' => $fingerprint ])
        )->fetch();

        if ($row !== false) {
            return $row['id'];
        }

        Logger::debug("Importing certificate: %s", $certInfo['name']);

        $pem = null;
        if (!openssl_x509_export($cert, $pem)) {
            die("Failed to encode X.509 certificate.");
        }
        $der = CertificateUtils::pem2der($pem);

        $signaturePieces = explode('-', $certInfo['signatureTypeSN']);

        $pubkeyDetails = openssl_pkey_get_details(openssl_pkey_get_public($cert));

        $ca = true;

        if (isset($certInfo['extensions'])) {
            $extensions = &$certInfo['extensions'];
            if (isset($extensions['basicConstraints'])) {
                $constraints = $extensions['basicConstraints'];

                $constraintPieces = explode(', ', $constraints);

                foreach ($constraintPieces as $constraintPiece) {
                    list($key, $value) = explode(':', $constraintPiece, 2);

                    if ($key == 'CA') {
                        $ca = ($value == 'TRUE');
                    }
                }
            }
        }

        $subjectDnHash = CertificateUtils::findOrInsertDn($db, $certInfo, 'subject');
        $issuerDnHash = CertificateUtils::findOrInsertDn($db, $certInfo, 'issuer');

        $db->insert(
            (new Insert())
                ->into('x509_certificate')
                ->values([
                    'subject'               => CertificateUtils::shortNameFromDN($certInfo['subject']),
                    'subject_hash'          => $subjectDnHash,
                    'issuer'                => CertificateUtils::shortNameFromDN($certInfo['issuer']),
                    'issuer_hash'           => $issuerDnHash,
                    'certificate'           => $der,
                    'fingerprint'           => $fingerprint,
                    'version'               => $certInfo['version'] + 1,
                    'serial'                => gmp_export($certInfo['serialNumber']),
                    'ca'                    => $ca ? 'yes' : 'no',
                    'pubkey_algo'           => CertificateUtils::$pubkeyTypes[$pubkeyDetails['type']],
                    'pubkey_bits'           => $pubkeyDetails['bits'],
                    'signature_algo'        => $signaturePieces[0],
                    'signature_hash_algo'   => $signaturePieces[1],
                    'valid_from'            => $certInfo['validFrom_time_t'],
                    'valid_to'              => $certInfo['validTo_time_t']
                ])
        );

        $certId = $db->lastInsertId();

        CertificateUtils::insertSANs($db, $certId, $certInfo);
        return $certId;
    }

    private static function insertSANs($db, $certId, array $certInfo) {
        if (isset($certInfo['extensions']['subjectAltName'])) {
            foreach (CertificateUtils::splitSANs($certInfo['extensions']['subjectAltName']) as $san) {
                list($type, $value) = $san;

                $row = $db->select(
                    (new Select())
                        ->from('x509_certificate_subject_alt_name')
                        ->columns('certificate_id')
                        ->where([
                            'certificate_id = ?' => $certId,
                            'type = ?' => $type,
                            'value = ?' => $value
                        ])
                )->fetch();

                // Ignore duplicate SANs
                if ($row !== false) {
                    continue;
                }

                $db->insert(
                    (new Insert())
                        ->into('x509_certificate_subject_alt_name')
                        ->columns(['certificate_id', 'type', 'value'])
                        ->values([$certId, $type, $value])
                );
            }
        }
    }

    private static function findOrInsertDn($db, $certInfo, $type) {
        $dn = $certInfo[$type];

        $data = '';
        foreach ($dn as $key => $value) {
            if (!is_array($value)) {
                $values = [$value];
            } else {
                $values = $value;
            }

            foreach ($values as $value) {
                $data .= "{$key}=${value}, ";
            }
        }
        $hash = hash('sha256', $data, true);

        $row = $db->select(
            (new Select())
                ->from('x509_dn')
                ->columns('hash')
                ->where([ 'hash = ?' => $hash, 'type = ?' => $type ])
                ->limit(1)
        )->fetch();

        if ($row !== false) {
            return $row['hash'];
        }

        $index = 0;
        foreach ($dn as $key => $value) {
            if (!is_array($value)) {
                $values = [$value];
            } else {
                $values = $value;
            }

            foreach ($values as $value) {
                $db->insert(
                    (new Insert())
                        ->into('x509_dn')
                        ->columns(['hash', '`key`', '`value`', '`order`', 'type'])
                        ->values([$hash, $key, $value, $index, $type])
                );
                $index++;
            }
        }

        return $hash;
    }

    public static function verifyCertificates($db) {
        $certs = $db->select(
            (new Select)
                ->from('x509_certificate')
                ->columns(['id', 'subject', 'issuer_hash', 'certificate'])
                ->where([ 'self_signed = ?' => 'no', 'issuer_certificate_id is null' ])
        );

        $count = 0;

        foreach ($certs as $cert) {
            $issuer_hash = $cert['issuer_hash'];
            $issuers = $db->select(
                (new Select)
                    ->from('x509_certificate')
                    ->columns([ 'id', 'subject', 'certificate' ])
                    ->where([ 'subject_hash = ?' => $issuer_hash ])
            );

            foreach ($issuers as $issuer) {
                Logger::debug("Potential issuer for cert %d is %d", $cert['id'], $issuer['id']);

                $issuerFile = tempnam('/tmp', 'issuer');

                if ($issuerFile === false) {
                    Logger::warn("Could not create temporary file in /tmp");
                    continue;
                }

                $subjectFile = tempnam('/tmp', 'subject');

                if ($subjectFile === false) {
                    unlink($issuerFile);
                    Logger::warn("Could not create temporary file in /tmp");
                    continue;
                }

                if (file_put_contents($issuerFile, CertificateUtils::der2pem($issuer['certificate'])) === false) {
                    unlink($issuerFile);
                    unlink($subjectFile);
                    Logger::warn("Could not write certificate file: %s", $issuerFile);
                    continue;
                }

                if (file_put_contents($subjectFile, CertificateUtils::der2pem($cert['certificate'])) === false) {
                    unlink($issuerFile);
                    unlink($subjectFile);
                    Logger::warn("Could not write certificate file: %s", $subjectFile);
                    continue;
                }

                $command = sprintf('openssl verify -no_check_time -partial_chain -CAfile %s %s >/dev/null', escapeshellarg($issuerFile), escapeshellarg($subjectFile));

                if (system($command, $status) === false) {
                    unlink($issuerFile);
                    unlink($subjectFile);
                    Logger::warn("Could not run 'openssl verify'");
                    continue;
                }

                unlink($issuerFile);
                unlink($subjectFile);

                if ($status !== 0) {
                    continue;
                }

                if ($cert['id'] == $issuer['id']) {
                    $opts = [ 'self_signed' => 'yes' ];
                } else {
                    $opts = [ 'issuer_certificate_id' => $issuer['id'] ];
                }

                $db->update(
                    (new Update())
                        ->table('x509_certificate')
                        ->set($opts)
                        ->where([ 'id = ?' => $cert['id'] ])
                );
            }

            $count++;
        }

        return $count;
    }
}
