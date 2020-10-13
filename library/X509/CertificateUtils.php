<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Exception;
use Icinga\Application\Logger;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use ipl\Sql\Connection;
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

    /**
     * Convert the given chunk from PEM to DER
     *
     * @param   string  $pem
     *
     * @return  string
     */
    public static function pem2der($pem)
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
    public static function der2pem($der)
    {
        $block = chunk_split(base64_encode($der), 64, "\n");

        return "-----BEGIN CERTIFICATE-----\n{$block}-----END CERTIFICATE-----";
    }

    /**
     * Format seconds to human-readable duration
     *
     * @param   int $seconds
     *
     * @return  string
     */
    public static function duration($seconds)
    {
        if ($seconds < 60) {
            return "$seconds Seconds";
        }

        if ($seconds < 3600) {
            $minutes = round($seconds / 60);

            return "$minutes Minutes";
        }

        if ($seconds < 86400) {
            $hours = round($seconds / 3600);

            return "$hours Hours";
        }

        if ($seconds < 604800) {
            $days = round($seconds / 86400);

            return "$days Days";
        }

        if ($seconds < 2592000) {
            $weeks = round($seconds / 604800);

            return "$weeks Weeks";
        }

        if ($seconds < 31536000) {
            $months = round($seconds / 2592000);

            return "$months Months";
        }

        $years = round($seconds / 31536000);

        return "$years Years";
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
            $cn = (array) $dn['CN'];
            return $cn[0];
        } else {
            $result = [];
            foreach ($dn as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $result[] = "{$key}={$item}";
                    }
                } else {
                    $result[] = "{$key}={$value}";
                }
            }

            return implode(', ', $result);
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

    /**
     * Find or insert the given certificate and return its ID
     *
     * @param   Connection  $db
     * @param   mixed       $cert
     *
     * @return  int
     */
    public static function findOrInsertCert(Connection $db, $cert)
    {
        $dbTool = new DbTool($db);

        $certInfo = openssl_x509_parse($cert);

        $fingerprint = openssl_x509_fingerprint($cert, 'sha256', true);

        $row = $db->select(
            (new Select())
                ->columns(['id'])
                ->from('x509_certificate')
                ->where(['fingerprint = ?' => $dbTool->marshalBinary($fingerprint)])
        )->fetch();

        if ($row !== false) {
            return (int) $row['id'];
        }

        Logger::debug("Importing certificate: %s", $certInfo['name']);

        $pem = null;
        if (! openssl_x509_export($cert, $pem)) {
            die('Failed to encode X.509 certificate.');
        }
        $der = CertificateUtils::pem2der($pem);

        $ca = false;
        if (isset($certInfo['extensions']['basicConstraints'])) {
            if (strpos($certInfo['extensions']['basicConstraints'], 'CA:TRUE') !== false) {
                $ca = true;
            }
        }

        $subjectHash = CertificateUtils::findOrInsertDn($db, $certInfo, 'subject');
        $issuerHash = CertificateUtils::findOrInsertDn($db, $certInfo, 'issuer');
        $pubkey = openssl_pkey_get_details(openssl_pkey_get_public($cert));
        $signature = explode('-', $certInfo['signatureTypeSN']);

        $db->insert(
            'x509_certificate',
            [
                'subject'             => CertificateUtils::shortNameFromDN($certInfo['subject']),
                'subject_hash'        => $dbTool->marshalBinary($subjectHash),
                'issuer'              => CertificateUtils::shortNameFromDN($certInfo['issuer']),
                'issuer_hash'         => $dbTool->marshalBinary($issuerHash),
                'version'             => $certInfo['version'] + 1,
                'self_signed'         => $subjectHash === $issuerHash ? 'yes' : 'no',
                'ca'                  => $ca ? 'yes' : 'no',
                'pubkey_algo'         => CertificateUtils::$pubkeyTypes[$pubkey['type']],
                'pubkey_bits'         => $pubkey['bits'],
                'signature_algo'      => array_shift($signature), // Support formats like RSA-SHA1 and
                'signature_hash_algo' => array_pop($signature),   // ecdsa-with-SHA384
                'valid_from'          => $certInfo['validFrom_time_t'],
                'valid_to'            => $certInfo['validTo_time_t'],
                'fingerprint'         => $dbTool->marshalBinary($fingerprint),
                'serial'              => $dbTool->marshalBinary(gmp_export($certInfo['serialNumber'])),
                'certificate'         => $dbTool->marshalBinary($der)
            ]
        );

        $certId = (int) $db->lastInsertId();

        CertificateUtils::insertSANs($db, $certId, $certInfo);

        return $certId;
    }


    private static function insertSANs($db, $certId, array $certInfo) {
        $dbTool = new DbTool($db);

        if (isset($certInfo['extensions']['subjectAltName'])) {
            foreach (CertificateUtils::splitSANs($certInfo['extensions']['subjectAltName']) as $san) {
                list($type, $value) = $san;

                $hash = hash('sha256', sprintf('%s=%s', $type, $value), true);

                $row = $db->select(
                    (new Select())
                        ->from('x509_certificate_subject_alt_name')
                        ->columns('certificate_id')
                        ->where([
                            'certificate_id = ?' => $certId,
                            'hash = ?' => $dbTool->marshalBinary($hash)
                        ])
                )->fetch();

                // Ignore duplicate SANs
                if ($row !== false) {
                    continue;
                }

                $db->insert(
                    'x509_certificate_subject_alt_name',
                    [
                        'certificate_id' => $certId,
                        'hash' => $dbTool->marshalBinary($hash),
                        'type' => $type,
                        'value' => $value
                    ]
                );
            }
        }
    }


    private static function findOrInsertDn($db, $certInfo, $type) {
        $dbTool = new DbTool($db);

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
                ->where([ 'hash = ?' => $dbTool->marshalBinary($hash), 'type = ?' => $type ])
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
                    'x509_dn',
                    [
                        'hash'    => $dbTool->marshalBinary($hash),
                        $db->quoteIdentifier('key')   => $key,
                        $db->quoteIdentifier('value') => $value,
                        $db->quoteIdentifier('order') => $index,
                        'type'    => $type
                    ]
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
        $dbTool = new DbTool($db);

        $files = new TemporaryLocalFileStorage();

        $caFile = uniqid('ca');

        $cas = $db->select(
            (new Select)
                ->from('x509_certificate')
                ->columns(['certificate'])
                ->where(['ca = ?' => 'yes', 'trusted = ?' => 'yes'])
        );

        $contents = [];

        foreach ($cas as $ca) {
            $contents[] = static::der2pem(DbTool::unmarshalBinary($ca['certificate']));
        }

        if (empty($contents)) {
            throw new \RuntimeException('Trust store is empty');
        }

        $files->create($caFile, implode("\n", $contents));

        $count = 0;

        $db->beginTransaction();

        try {
            $chains = $db->select(
                (new Select)
                    ->from('x509_certificate_chain c')
                    ->join('x509_target t', ['t.latest_certificate_chain_id = c.id', 'c.valid = ?' => 'no'])
                    ->columns('c.id')
            );

            foreach ($chains as $chain) {
                ++$count;

                $certs = $db->select(
                    (new Select)
                        ->from('x509_certificate c')
                        ->columns('c.certificate')
                        ->join('x509_certificate_chain_link ccl', 'ccl.certificate_id = c.id')
                        ->where(['ccl.certificate_chain_id = ?' => $chain['id']])
                        ->orderBy(['ccl.order' => 'DESC'])
                );

                $collection = [];

                foreach ($certs as $cert) {
                    $collection[] = CertificateUtils::der2pem(DbTool::unmarshalBinary($cert['certificate']));
                }

                $certFile = uniqid('cert');

                $files->create($certFile, array_pop($collection));

                $untrusted = '';
                foreach ($collection as $intermediate) {
                    $intermediateFile = uniqid('intermediate');
                    $files->create($intermediateFile, $intermediate);
                    $untrusted .= ' -untrusted ' . escapeshellarg($files->resolvePath($intermediateFile));
                }

                $command = sprintf(
                    'openssl verify -CAfile %s%s %s 2>&1',
                    escapeshellarg($files->resolvePath($caFile)),
                    $untrusted,
                    escapeshellarg($files->resolvePath($certFile))
                );

                $output = null;

                exec($command, $output, $exitcode);

                $output = implode("\n", $output);

                if ($exitcode !== 0) {
                    Logger::warning('openssl verify failed for command %s: %s', $command, $output);
                }

                preg_match('/^error \d+ at \d+ depth lookup:(.+)$/m', $output, $match);

                if (!empty($match)) {
                    $set = ['invalid_reason' => $match[1]];
                } else {
                    $set = ['valid' => 'yes'];
                }

                $db->update(
                    'x509_certificate_chain',
                    $set,
                    ['id = ?' => $chain['id']]
                );
            }

            $db->commitTransaction();
        } catch (Exception $e) {
            Logger::error($e);
            $db->rollBackTransaction();
        }

        return $count;
    }
}
