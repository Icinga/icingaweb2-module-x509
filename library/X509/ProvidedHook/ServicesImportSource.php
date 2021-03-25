<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook;

use ipl\Sql;

class ServicesImportSource extends x509ImportSource
{
    public function fetchData()
    {
        if ($this->getDb()->getConfig()->db === 'pgsql') {
            $targets = (new Sql\Select())
                ->from(['x509_target t'])
                ->columns([
                    'host_ip'               => 'ENCODE(t.ip, \'hex\')',
                    'host_name'             => 't.hostname',
                    'host_port'             => 't.port',
                    'cert_subject'          => 'c.subject',
                    'cert_issuer'           => 'c.issuer',
                    'cert_self_signed'      => 'COALESCE(ci.self_signed, c.self_signed)',
                    'cert_trusted'          => 'c.trusted',
                    'cert_valid_from'       => 'c.valid_from',
                    'cert_valid_to'         => 'c.valid_to',
                    'cert_fingerprint'      => 'ENCODE(c.fingerprint, \'hex\')',
                    'cert_dn'               => 'ARRAY_TO_STRING(ARRAY_AGG(CONCAT(dn.key, \'=\', dn.value)), \',\')',
                    'cert_subject_alt_name' => (new Sql\Select())
                        ->from('x509_certificate_subject_alt_name can')
                        ->columns('ARRAY_TO_STRING(ARRAY_AGG(CONCAT(can.type, \':\', can.value)), \',\')')
                        ->where(['can.certificate_id = c.id'])
                        ->groupBy(['can.certificate_id'])
                ])
                ->join('x509_certificate_chain cc', 'cc.id = t.latest_certificate_chain_id')
                ->join('x509_certificate_chain_link ccl', 'ccl.certificate_chain_id = cc.id')
                ->join('x509_certificate c', 'c.id = ccl.certificate_id')
                ->joinLeft('x509_certificate ci', 'ci.subject_hash = c.issuer_hash')
                ->joinLeft('x509_dn dn', 'dn.hash = c.subject_hash')
                ->where(['ccl.order = ?' => 0])
                ->groupBy(['t.ip', 't.hostname', 't.port', 'c.id', 'ci.id']);
        } else {
            $targets = (new Sql\Select())
                ->from('x509_target t')
                ->columns([
                    'host_ip'               => 't.ip',
                    'host_name'             => 't.hostname',
                    'host_port'             => 't.port',
                    'cert_subject'          => 'c.subject',
                    'cert_issuer'           => 'c.issuer',
                    'cert_self_signed'      => 'COALESCE(ci.self_signed, c.self_signed)',
                    'cert_trusted'          => 'c.trusted',
                    'cert_valid_from'       => 'c.valid_from',
                    'cert_valid_to'         => 'c.valid_to',
                    'cert_fingerprint'      => 'HEX(c.fingerprint)',
                    'cert_dn'               => 'GROUP_CONCAT(CONCAT(dn.key, \'=\', dn.value) SEPARATOR \',\')',
                    'cert_subject_alt_name' => (new Sql\Select())
                        ->from('x509_certificate_subject_alt_name can')
                        ->columns('GROUP_CONCAT(CONCAT(can.type, \':\', can.value) SEPARATOR \',\')')
                        ->where(['can.certificate_id = c.id'])
                        ->groupBy(['can.certificate_id'])
                ])
                ->join('x509_certificate_chain cc', 'cc.id = t.latest_certificate_chain_id')
                ->join('x509_certificate_chain_link ccl', 'ccl.certificate_chain_id = cc.id')
                ->join('x509_certificate c', 'c.id = ccl.certificate_id')
                ->joinLeft('x509_certificate ci', 'ci.subject_hash = c.issuer_hash')
                ->joinLeft('x509_dn dn', 'dn.hash = c.subject_hash')
                ->where(['ccl.order = ?' => 0])
                ->groupBy(['t.ip', 't.hostname', 't.port']);
        }

        $results = [];
        foreach ($this->getDb()->select($targets) as $target) {
            if ($this->getDb()->getConfig()->db === 'pgsql') {
                $target->host_ip = hex2bin($target->host_ip);
            }
            
            list($ipv4, $ipv6) = $this->transformIpAddress($target->host_ip);
            $target->host_ip = $ipv4 ?: $ipv6;
            $target->host_address = $ipv4;
            $target->host_address6 = $ipv6;

            $target->host_name_ip_and_port = sprintf(
                '%s/%s:%d',
                $target->host_name,
                $target->host_ip,
                $target->host_port
            );

            $results[$target->host_name_ip_and_port] = $target;
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
