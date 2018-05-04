<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509;

use Icinga\Application\Logger;
use ipl\Sql\Connection;
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

    /**
     * Yield certificates in the given bundle
     *
     * @param   string  $file   Path to the bundle
     *
     * @return  \Generator
     */
    public static function parseBundle($file)
    {
        $content = file_get_contents($file);

        $blocks = explode('-----BEGIN CERTIFICATE-----', $content);

        foreach ($blocks as $block) {
            $end = strrpos($block, '-----END CERTIFICATE-----');

            if ($end !== false) {
                yield '-----BEGIN CERTIFICATE-----' . substr($block, 0, $end) . '-----END CERTIFICATE-----';
            }
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

   /**
    * Verify certificates
    *
    * @param   Connection   $db   Connection to the X.509 database
    *
    * @return  int
    */
    public static function verifyCertificates(Connection $db)
    {
        $certs = $db->select(
            (new Select)
                ->from('x509_certificate')
                ->columns(['id', 'subject', 'issuer_hash', 'certificate'])
                ->where(['issuer_certificate_id IS NULL'])
        );

        $tempdir = sys_get_temp_dir();

        $issuerFile = tempnam($tempdir, 'issuer');
        $certFile = tempnam($tempdir, 'cert');

        register_shutdown_function(function () use ($issuerFile, $certFile) {
            if (is_resource($issuerFile)) {
                unlink($issuerFile);
            }

            if (is_resource($certFile)) {
                unlink($certFile);
            }
        });

        if ($issuerFile === false || $certFile === false) {
            Logger::error('Could not create temporary file in %s', $tempdir);
            return 0;
        }

        $count = 0;

        foreach ($certs as $cert) {
            $issuers = $db->select(
                (new Select)
                    ->from('x509_certificate')
                    ->columns(['id', 'subject', 'certificate'])
                    ->where(['subject_hash = ?' => $cert['issuer_hash']])
            );

            foreach ($issuers as $issuer) {
                Logger::debug('Potential issuer for cert %d is %d', $cert['id'], $issuer['id']);

                if (file_put_contents($issuerFile, CertificateUtils::der2pem($issuer['certificate'])) === false
                    || file_put_contents($certFile, CertificateUtils::der2pem($cert['certificate'])) === false
                ) {
                    Logger::warning('Can\'t write certificate file');
                    continue;
                }

                $command = sprintf(
                    'openssl verify -no_check_time -partial_chain -CAfile %s %s 2>&1',
                    escapeshellarg($issuerFile),
                    escapeshellarg($certFile)
                );

                $output = null;

                exec($command, $output, $exitcode);

                if ($exitcode !== 0) {
                    Logger::warning('openssl verify failed for command %s: %s', $command, implode(PHP_EOL, $output));
                    continue;
                }

                $set = ['issuer_certificate_id' => $issuer['id']];

                if ($cert['id'] === $issuer['id']) {
                    $set['self_signed'] = 'yes';
                }

                $db->update(
                    (new Update())
                        ->table('x509_certificate')
                        ->set($set)
                        ->where(['id = ?' => $cert['id']])
                );
            }

            $count++;
        }

        return $count;
    }
}
