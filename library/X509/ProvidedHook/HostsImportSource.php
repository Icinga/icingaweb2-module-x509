<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook;

use ipl\Sql;

class HostsImportSource extends x509ImportSource
{
    public function fetchData()
    {
        $targets = (new Sql\Select())
            ->from('x509_target t')
            ->columns([
                'host_ip'       => 't.ip',
                'host_name'     => 't.hostname',
                'host_ports'    => 'GROUP_CONCAT(DISTINCT t.port SEPARATOR ",")'
            ])
            ->join('x509_certificate_chain cc', 'cc.id = t.latest_certificate_chain_id')
            ->join('x509_certificate_chain_link ccl', 'ccl.certificate_chain_id = cc.id')
            ->join('x509_certificate c', 'c.id = ccl.certificate_id')
            ->where(['ccl.order = ?' => 0])
            ->groupBy(['t.ip', 't.hostname']);

        $results = [];
        $foundDupes = [];
        foreach ($this->getDb()->select($targets) as $target) {
            list($ipv4, $ipv6) = $this->transformIpAddress($target->host_ip);
            $target->host_ip = $ipv4 ?: $ipv6;
            $target->host_address = $ipv4;
            $target->host_address6 = $ipv6;

            if (isset($foundDupes[$target->host_name])) {
                // For load balanced systems the IP address is the better choice
                $target->host_name_or_ip = $target->host_ip;
            } elseif (! isset($results[$target->host_name])) {
                // Hostnames are usually preferred, especially in the case of SNI
                $target->host_name_or_ip = $target->host_name;
            } else {
                $dupe = $results[$target->host_name];
                unset($results[$target->host_name]);
                $foundDupes[$dupe->host_name] = true;
                $dupe->host_name_or_ip = $dupe->host_ip;
                $results[$dupe->host_name_or_ip] = $dupe;
                $target->host_name_or_ip = $target->host_ip;
            }

            $results[$target->host_name_or_ip] = $target;
        }

        return $results;
    }

    public function listColumns()
    {
        return [
            'host_name_or_ip',
            'host_ip',
            'host_name',
            'host_ports',
            'host_address',
            'host_address6'
        ];
    }

    public static function getDefaultKeyColumnName()
    {
        return 'host_name_or_ip';
    }
}
