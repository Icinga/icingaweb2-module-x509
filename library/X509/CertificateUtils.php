<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509;

use Icinga\Application\Logger;
use ipl\Sql\Insert;
use ipl\Sql\Select;

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

    private static function pem2der($pem) {
        $lines = explode("\n", $pem);

        $der = '';

        foreach ($lines as $line) {
            if (strstr($line, '-----') === 0) {
                continue;
            }

            $der .= base64_decode($line);
        }

        return $der;
    }

    private static function shortNameFromDN($dn) {
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

    public static function findOrInsertCert($db, $cert) {
        $certInfo = openssl_x509_parse($cert);

        $fingerprint = openssl_x509_fingerprint($cert, 'sha256', true);

        $row = $db->select(
            (new Select())
                ->columns(['id'])
                ->from('certificate')
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

        $db->insert(
            (new Insert())
                ->into('certificate')
                ->values([
                    'subject'               => CertificateUtils::shortNameFromDN($certInfo['subject']),
                    'issuer'                => CertificateUtils::shortNameFromDN($certInfo['issuer']),
                    'certificate'           => $der,
                    'fingerprint'           => $fingerprint,
                    'version'               => $certInfo['version'] + 1,
                    'serial'                => gmp_export($certInfo['serialNumber']),
                    'ca'                    => $ca ? 'yes' : 'no',
                    'pubkey_algo'           => CertificateUtils::$pubkeyTypes[$pubkeyDetails['type']],
                    'pubkey_bits'           => $pubkeyDetails['bits'],
                    'signature_algo'        => $signaturePieces[0],
                    'signature_hash_algo'   => $signaturePieces[1],
                    'valid_start'           => $certInfo['validFrom_time_t'],
                    'valid_end'             => $certInfo['validTo_time_t']
                ])
        );

        $certId = $db->lastInsertId();

        CertificateUtils::insertDn($db, $certId, 'issuer', $certInfo);
        CertificateUtils::insertDn($db, $certId, 'subject', $certInfo);

        CertificateUtils::insertSANs($db, $certId, $certInfo);
        return $certId;
    }

    private static function splitSANs($sans) {
        preg_match_all('/(?:^|, )([^:]+):/', $sans, $keys);
        $values = preg_split('/(^|, )[^:]+:/', $sans);
        for ($i = 0; $i < count($keys[1]); $i++) {
            yield [ $keys[1][$i], $values[$i + 1] ];
        }
    }

    private static function insertSANs($db, $certId, array $certInfo) {
        if (isset($certInfo['extensions']['subjectAltName'])) {
            foreach (CertificateUtils::splitSANs($certInfo['extensions']['subjectAltName']) as $san) {
                list($type, $value) = $san;

                $row = $db->select(
                    (new Select())
                        ->from('certificate_subject_alt_name')
                        ->columns('certificate_id')
                        ->where([
                            'certificate_id = ?' => $certId,
                            'type = ?' => $type,
                            'value = ?' => $value
                        ])
                );

                // Ignore duplicate SANs
                if ($row !== false) {
                    continue;
                }

                $db->insert(
                    (new Insert())
                        ->into('certificate_subject_alt_name')
                        ->columns(['certificate_id', 'type', 'value'])
                        ->values([$certId, $type, $value])
                );
            }
        }
    }

    private static function insertDn($db, $certId, $type, array $certInfo) {
        $index = 0;
        foreach ($certInfo[$type] as $key => $value) {
            if (!is_array($value)) {
                $values = [$value];
            } else {
                $values = $value;
            }

            foreach ($values as $value) {
                $db->insert(
                    (new Insert())
                        ->into("certificate_{$type}_dn")
                        ->columns(['certificate_id', '`key`', '`value`', '`order`'])
                        ->values([$certId, $key, $value, $index])
                );
                $index++;
            }
        }
    }

}