<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Exception;
use Icinga\Application\Logger;
use Icinga\File\Storage\TemporaryLocalFileStorage;
use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Module\X509\Model\X509CertificateSubjectAltName;
use Icinga\Module\X509\Model\X509Dn;
use Icinga\Module\X509\Model\X509Target;
use ipl\Orm\Model;
use ipl\Sql\Connection;
use ipl\Sql\Expression;
use ipl\Sql\Select;
use ipl\Stdlib\Filter;
use ipl\Stdlib\Str;

use function ipl\Stdlib\yield_groups;

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
     * @param array $dn
     *
     * @return  string  The CN if it exists or the full DN as string
     */
    private static function shortNameFromDN(array $dn): string
    {
        if (isset($dn['CN'])) {
            return ((array) $dn['CN'])[0];
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
     * @param ?string $sanStr
     *
     * @return  array
     */
    public static function splitSANs(?string $sanStr): array
    {
        $sans = [];
        foreach (Str::trimSplit($sanStr) as $altName) {
            if (strpos($altName, ':') === false) {
                [$k, $v] = Str::trimSplit($altName, '=', 2);
            } else {
                [$k, $v] = Str::trimSplit($altName, ':', 2);
            }

            $sans[$k][] = $v;
        }

        $order = array_flip(['DNS', 'URI', 'IP Address', 'email', 'DirName']);
        uksort($sans, function ($a, $b) use ($order) {
            return ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX);
        });

        return $sans;
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

        $sans = static::splitSANs($certInfo['extensions']['subjectAltName'] ?? null);
        if (! isset($certInfo['subject']['CN']) && ! empty($sans)) {
            $subject = current($sans)[0];
        } else {
            $subject = self::shortNameFromDN($certInfo['subject']);
        }

        // TODO: https://github.com/Icinga/ipl-orm/pull/78
        $db->insert(
            'x509_certificate',
            [
                'subject'             => $subject,
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

        CertificateUtils::insertSANs($db, $certId, $sans);

        return [$certId, $issuerHash];
    }

    private static function insertSANs($db, $certId, iterable $sans): void
    {
        $dbTool = new DbTool($db);
        foreach ($sans as $type => $values) {
            foreach ($values as $value) {
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
                $data .= "$key=$value, ";
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
     * Remove certificates that are no longer in use
     *
     * Remove chains that aren't used by any target, certificates that aren't part of any chain, and DNs
     * that aren't used anywhere.
     *
     * @param Connection $conn
     */
    public static function cleanupNoLongerUsedCertificates(Connection $conn)
    {
        $chainQuery = $conn->delete(
            'x509_certificate_chain',
            ['id NOT IN ?' => X509Target::on($conn)->columns('latest_certificate_chain_id')->assembleSelect()]
        );

        $rows = $chainQuery->rowCount();
        if ($rows > 0) {
            Logger::info('Removed %d certificate chains that are not used by any targets', $rows);
        }

        $certsQuery = $conn->delete('x509_certificate', [
            'id NOT IN ?' => (new Select())
                ->from('x509_certificate_chain_link ccl')
                ->columns(['ccl.certificate_id'])
                ->distinct(),
            'trusted = ?' => 'n',
        ]);

        $rows = $certsQuery->rowCount();
        if ($rows > 0) {
            Logger::info('Removed %d certificates that are not part of any chains', $rows);
        }

        $dnQuery = $conn->delete('x509_dn', [
            'hash NOT IN ?' => X509Certificate::on($conn)->columns('subject_hash')->assembleSelect()
        ]);

        $rows = $dnQuery->rowCount();
        if ($rows > 0) {
            Logger::info('Removed %d DNs that are not used anywhere', $rows);
        }
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
        /** @var Model $ca */
        foreach ($cas as $ca) {
            $contents[] = $ca->certificate;
        }

        if (empty($contents)) {
            throw new \RuntimeException('Trust store is empty');
        }

        $files->create($caFile, implode("\n", $contents));

        $count = 0;
        $certs = X509Certificate::on($db)
            ->with(['chain'])
            ->utilize('chain.target')
            ->columns(['chain.id', 'certificate'])
            ->filter(Filter::equal('chain.valid', false))
            ->orderBy('chain.id')
            ->orderBy(new Expression('certificate_link.order'), SORT_DESC);

        $db->beginTransaction();

        try {
            $caFile = escapeshellarg($files->resolvePath($caFile));
            $verifyCertsFunc = function (int $chainId, array $collection) use ($db, $caFile) {
                $certFiles = new TemporaryLocalFileStorage();
                $certFile = uniqid('cert');
                $certFiles->create($certFile, array_pop($collection));

                $untrusted = '';
                if (! empty($collection)) {
                    $intermediateFile = uniqid('intermediate');
                    $certFiles->create($intermediateFile, implode("\n", $collection));

                    $untrusted = sprintf(
                        ' -untrusted %s',
                        escapeshellarg($certFiles->resolvePath($intermediateFile))
                    );
                }

                $command = sprintf(
                    'openssl verify -CAfile %s%s %s 2>&1',
                    $caFile,
                    $untrusted,
                    escapeshellarg($certFiles->resolvePath($certFile))
                );

                $output = null;

                exec($command, $output, $exitcode);

                $output = implode("\n", $output);

                if ($exitcode !== 0) {
                    Logger::debug('openssl verify failed for command %s: %s', $command, $output);
                }

                preg_match('/^error \d+ at \d+ depth lookup:(.+)$/m', $output, $match);

                if (! empty($match)) {
                    $set = ['invalid_reason' => trim($match[1])];
                } else {
                    $set = ['valid' => 'y', 'invalid_reason' => null];
                }

                // TODO: https://github.com/Icinga/ipl-orm/pull/78
                $db->update('x509_certificate_chain', $set, ['id = ?' => $chainId]);
            };

            $groupBy = function (X509Certificate $cert): array {
                // Group all the certificates by their chain id.
                return [$cert->chain->id, $cert->certificate];
            };

            foreach (yield_groups($certs, $groupBy) as $chainId => $collection) {
                ++$count;
                $verifyCertsFunc($chainId, $collection);
            }

            $db->commitTransaction();
        } catch (Exception $e) {
            Logger::error($e);
            $db->rollBackTransaction();
        }

        return $count;
    }
}
