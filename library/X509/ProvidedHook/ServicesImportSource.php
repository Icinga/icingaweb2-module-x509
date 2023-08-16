<?php

// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook;

use Icinga\Module\X509\Job;
use Icinga\Module\X509\Model\X509CertificateSubjectAltName;
use Icinga\Module\X509\Model\X509Target;
use ipl\Sql;

class ServicesImportSource extends X509ImportSource
{
    public function fetchData()
    {
        $targets = X509Target::on($this->getDb())
            ->with([
                'chain',
                'chain.certificate',
                'chain.certificate.dn',
                'chain.certificate.issuer_certificate'
            ])
            ->columns([
                'ip',
                'host_name'        => 'hostname',
                'host_port'        => 'port',
                'cert_subject'     => 'chain.certificate.subject',
                'cert_issuer'      => 'chain.certificate.issuer',
                'cert_trusted'     => 'chain.certificate.trusted',
                'cert_valid_from'  => 'chain.certificate.valid_from',
                'cert_valid_to'    => 'chain.certificate.valid_to',
                'cert_self_signed' => new Sql\Expression('COALESCE(%s, %s)', [
                    'chain.certificate.issuer_certificate.self_signed',
                    'chain.certificate.self_signed'
                ])
            ]);

        $targets->getWith()['target.chain.certificate.issuer_certificate']->setJoinType('LEFT');
        $targets
            ->getSelectBase()
            ->where(new Sql\Expression('target_chain_link.order = 0'))
            ->groupBy(['ip, hostname, port']);

        $certAltName = X509CertificateSubjectAltName::on($this->getDb());
        $certAltName
            ->getSelectBase()
            ->where(new Sql\Expression('certificate_id = target_chain_certificate.id'))
            ->groupBy(['alt_name.certificate_id']);

        if ($this->getDb()->getAdapter() instanceof Sql\Adapter\Pgsql) {
            $targets
                ->withColumns([
                    'cert_fingerprint' => new Sql\Expression("ENCODE(%s, 'hex')", [
                        'chain.certificate.fingerprint'
                    ]),
                    'cert_dn'          => new Sql\Expression(
                        "ARRAY_TO_STRING(ARRAY_AGG(CONCAT(%s, '=', %s)), ',')",
                        [
                            'chain.certificate.dn.key',
                            'chain.certificate.dn.value'
                        ]
                    )
                ])
                ->getSelectBase()
                ->groupBy(['target_chain_certificate.id', 'target_chain_certificate_issuer_certificate.id']);

            $certAltName->columns([
                new Sql\Expression("ARRAY_TO_STRING(ARRAY_AGG(CONCAT(%s, ':', %s)), ',')", ['type', 'value'])
            ]);
        } else {
            $targets->withColumns([
                'cert_fingerprint' => new Sql\Expression('HEX(%s)', ['chain.certificate.fingerprint']),
                'cert_dn'          => new Sql\Expression(
                    "GROUP_CONCAT(CONCAT(%s, '=', %s) SEPARATOR ',')",
                    [
                        'chain.certificate.dn.key',
                        'chain.certificate.dn.value'
                    ]
                )
            ]);

            $certAltName->columns([
                new Sql\Expression("GROUP_CONCAT(CONCAT(%s, ':', %s) SEPARATOR ',')", ['type', 'value'])
            ]);
        }

        list($select, $values) = $certAltName->dump();
        $targets->withColumns(['cert_subject_alt_name' => new Sql\Expression("$select", null, ...$values)]);

        $results = [];
        /** @var X509Target $target */
        foreach ($targets as $target) {
            $isV6 = Job::isIPV6($target->ip);
            $target->host_ip = $target->ip;
            $target->host_address = $isV6 ? null : $target->ip;
            $target->host_address6 = $isV6 ? $target->ip : null;

            $target->host_name_ip_and_port = sprintf(
                '%s/%s:%d',
                $target->host_name,
                $target->host_ip,
                $target->host_port
            );

            // Target ip is now obsolete and must not be included in the results.
            // The relation is only used to utilize the query and must not be in the result set as well.
            unset($target->ip);
            unset($target->chain);

            $results[$target->host_name_ip_and_port] = (object) iterator_to_array($target);
        }

        return $results;
    }

    public function listColumns()
    {
        return [
            'host_name_ip_and_port',
            'host_ip',
            'host_name',
            'host_port',
            'host_address',
            'host_address6',
            'cert_subject',
            'cert_issuer',
            'cert_self_signed',
            'cert_trusted',
            'cert_valid_from',
            'cert_valid_to',
            'cert_fingerprint',
            'cert_dn',
            'cert_subject_alt_name'
        ];
    }

    public static function getDefaultKeyColumnName()
    {
        return 'host_name_ip_and_port';
    }
}
