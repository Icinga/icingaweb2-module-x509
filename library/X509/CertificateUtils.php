<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509;

use Exception;
use Icinga\Application\Logger;
use Icinga\File\Storage\TemporaryLocalFileStorage;
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
        $certInfo = openssl_x509_parse($cert);

        $fingerprint = openssl_x509_fingerprint($cert, 'sha256', true);

        $row = $db->select(
            (new Select())
                ->columns(['id'])
                ->from('x509_certificate')
                ->where(['fingerprint = ?' => $fingerprint])
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
            (new Insert())
                ->into('x509_certificate')
                ->values([
                    'subject'             => CertificateUtils::shortNameFromDN($certInfo['subject']),
                    'subject_hash'        => $subjectHash,
                    'issuer'              => CertificateUtils::shortNameFromDN($certInfo['issuer']),
                    'issuer_hash'         => $issuerHash,
                    'version'             => $certInfo['version'] + 1,
                    'self_signed'         => $subjectHash === $issuerHash ? 'yes' : 'no',
                    'ca'                  => $ca ? 'yes' : 'no',
                    'pubkey_algo'         => CertificateUtils::$pubkeyTypes[$pubkey['type']],
                    'pubkey_bits'         => $pubkey['bits'],
                    'signature_algo'      => $signature[0],
                    'signature_hash_algo' => $signature[1],
                    'valid_from'          => $certInfo['validFrom_time_t'],
                    'valid_to'            => $certInfo['validTo_time_t'],
                    'fingerprint'         => $fingerprint,
                    'serial'              => gmp_export($certInfo['serialNumber']),
                    'certificate'         => $der
                ] )
        );

        $certId = (int) $db->lastInsertId();

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
            $contents[] = static::der2pem($ca['certificate']);
        }

        $files->create($caFile, implode("\n", $contents));

        $count = 0;

        $db->beginTransaction();

        try {
            $db->update(
                (new Update())
                    ->table('x509_certificate_chain')
                    ->set(['valid' => 'no'])
            );

            $chains = $db->select(
                (new Select)
                    ->from('x509_certificate_chain')
                    ->columns('id')
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
                    $collection[] = CertificateUtils::der2pem($cert['certificate']);
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
                    (new Update())
                        ->table('x509_certificate_chain')
                        ->set($set)
                        ->where(['id = ?' => $chain['id']])
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
