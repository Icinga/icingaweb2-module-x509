<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Exception;
use Icinga\Application\Logger;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Module\X509\Model\X509CertificateChain;
use Icinga\Module\X509\Model\X509CertificateSubjectAltName;
use Icinga\Module\X509\Model\X509Dn;
use ipl\Sql\Connection;
use ipl\Sql\Expression;
use ipl\Stdlib\Filter;

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
     * @return  array
     */
    public static function findOrInsertCert(Connection $db, $cert)
    {
        $dbTool = new DbTool($db);

        $certInfo = openssl_x509_parse($cert);

        $fingerprint = openssl_x509_fingerprint($cert, 'sha256', true);

        $row = X509Certificate::on($db);
        $row
            ->columns(['id', 'issuer_hash'])
            ->filter(Filter::equal('fingerprint', $fingerprint));

        $row = $row->first();
        if ($row) {
            return [$row->id, $row->issuer_hash];
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

        // TODO: https://github.com/Icinga/ipl-orm/pull/78
        $db->insert(
            'x509_certificate',
            [
                'subject'             => CertificateUtils::shortNameFromDN($certInfo['subject']),
                'subject_hash'        => $dbTool->marshalBinary($subjectHash),
                'issuer'              => CertificateUtils::shortNameFromDN($certInfo['issuer']),
                'issuer_hash'         => $dbTool->marshalBinary($issuerHash),
                'version'             => $certInfo['version'] + 1,
                'self_signed'         => $subjectHash === $issuerHash ? 'y' : 'n',
                'ca'                  => $ca ? 'y' : 'n',
                'pubkey_algo'         => CertificateUtils::$pubkeyTypes[$pubkey['type']],
                'pubkey_bits'         => $pubkey['bits'],
                'signature_algo'      => array_shift($signature), // Support formats like RSA-SHA1 and
                'signature_hash_algo' => array_pop($signature),   // ecdsa-with-SHA384
                'valid_from'          => $certInfo['validFrom_time_t'] * 1000.0,
                'valid_to'            => $certInfo['validTo_time_t'] * 1000.0,
                'fingerprint'         => $dbTool->marshalBinary($fingerprint),
                'serial'              => $dbTool->marshalBinary(gmp_export($certInfo['serialNumber'])),
                'certificate'         => $dbTool->marshalBinary($der),
                'ctime'               => new Expression('UNIX_TIMESTAMP() * 1000')
            ]
        );

        $certId = $db->lastInsertId();

        CertificateUtils::insertSANs($db, $certId, $certInfo);

        return [$certId, $issuerHash];
    }


    private static function insertSANs($db, $certId, array $certInfo)
    {
        $dbTool = new DbTool($db);

        if (isset($certInfo['extensions']['subjectAltName'])) {
            foreach (CertificateUtils::splitSANs($certInfo['extensions']['subjectAltName']) as $san) {
                list($type, $value) = $san;

                $hash = hash('sha256', sprintf('%s=%s', $type, $value), true);

                $row = X509CertificateSubjectAltName::on($db);
                $row->columns([new Expression('1')]);

                $filter = Filter::all(
                    Filter::equal('certificate_id', $certId),
                    Filter::equal('hash', $hash)
                );

                $row->filter($filter);

                // Ignore duplicate SANs
                if ($row->execute()->hasResult()) {
                    continue;
                }

                // TODO: https://github.com/Icinga/ipl-orm/pull/78
                $db->insert(
                    'x509_certificate_subject_alt_name',
                    [
                        'certificate_id' => $certId,
                        'hash'           => $dbTool->marshalBinary($hash),
                        'type'           => $type,
                        'value'          => $value,
                        'ctime'          => new Expression('UNIX_TIMESTAMP() * 1000')
                    ]
                );
            }
        }
    }


    private static function findOrInsertDn($db, $certInfo, $type)
    {
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

        $row = X509Dn::on($db);
        $row
            ->columns(['hash'])
            ->filter(Filter::all(
                Filter::equal('hash', $hash),
                Filter::equal('type', $type)
            ))
            ->limit(1);

        $row = $row->first();
        if ($row) {
            return $row->hash;
        }

        $index = 0;
        foreach ($dn as $key => $value) {
            if (!is_array($value)) {
                $values = [$value];
            } else {
                $values = $value;
            }

            foreach ($values as $value) {
                // TODO: https://github.com/Icinga/ipl-orm/pull/78
                $db->insert(
                    'x509_dn',
                    [
                        'hash'    => $dbTool->marshalBinary($hash),
                        $db->quoteIdentifier('key')   => $key,
                        $db->quoteIdentifier('value') => $value,
                        $db->quoteIdentifier('order') => $index,
                        'type'    => $type,
                        'ctime'   => new Expression('UNIX_TIMESTAMP() * 1000')
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
        $files = new TemporaryLocalFileStorage();

        $caFile = uniqid('ca');

        $cas = X509Certificate::on($db);
        $cas
            ->columns(['certificate'])
            ->filter(Filter::all(
                Filter::equal('ca', true),
                Filter::equal('trusted', true)
            ));

        $contents = [];

        foreach ($cas as $ca) {
            $contents[] = $ca->certificate;
        }

        if (empty($contents)) {
            throw new \RuntimeException('Trust store is empty');
        }

        $files->create($caFile, implode("\n", $contents));

        $count = 0;

        $db->beginTransaction();

        try {
            $chains = X509CertificateChain::on($db)->utilize('target');
            $chains
                ->columns(['id'])
                ->filter(Filter::equal('valid', false));

            foreach ($chains as $chain) {
                ++$count;

                $certs = X509Certificate::on($db)->utilize('chain');
                $certs
                    ->columns(['certificate'])
                    ->filter(Filter::equal('chain.id', $chain->id))
                    ->getSelectBase()
                    ->orderBy('certificate_link.order', 'DESC');

                $collection = [];

                foreach ($certs as $cert) {
                    $collection[] = $cert->certificate;
                }

                $certFile = uniqid('cert');

                $files->create($certFile, array_pop($collection));

                $untrusted = '';

                if (! empty($collection)) {
                    $intermediateFile = uniqid('intermediate');
                    $files->create($intermediateFile, implode("\n", $collection));

                    $untrusted = sprintf(
                        ' -untrusted %s',
                        escapeshellarg($files->resolvePath($intermediateFile))
                    );
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
                    Logger::debug('openssl verify failed for command %s: %s', $command, $output);
                }

                preg_match('/^error \d+ at \d+ depth lookup:(.+)$/m', $output, $match);

                if (!empty($match)) {
                    $set = ['invalid_reason' => trim($match[1])];
                } else {
                    $set = ['valid' => 'y', 'invalid_reason' => null];
                }

                // TODO: https://github.com/Icinga/ipl-orm/pull/78
                $db->update(
                    'x509_certificate_chain',
                    $set,
                    ['id = ?' => $chain->id]
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
