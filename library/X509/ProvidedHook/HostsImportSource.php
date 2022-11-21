<?php

// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook;

use Icinga\Module\X509\DbTool;
use Icinga\Module\X509\Job;
use Icinga\Module\X509\Model\X509Target;
use ipl\Sql;
use ipl\Stdlib\Filter;

class HostsImportSource extends X509ImportSource
{
    public function fetchData()
    {
        $targets = X509Target::on($this->getDb())
            ->utilize('chain')
            ->utilize('chain.certificate')
            ->columns([
                'ip',
                'host_name' => 'hostname'
            ]);

        $targets
            ->getSelectBase()
            ->where(new Sql\Expression('target_chain_link.order = 0'))
            ->groupBy(['ip', 'hostname']);

        if ($this->getDb()->getAdapter() instanceof Sql\Adapter\Pgsql) {
            $targets->withColumns([
                'host_ports' => new Sql\Expression('ARRAY_TO_STRING(ARRAY_AGG(DISTINCT port),  \',\')')
            ]);
        } else {
            $targets->withColumns([
                'host_ports' => new Sql\Expression('GROUP_CONCAT(DISTINCT port SEPARATOR \',\')')
            ]);
        }

        $results = [];
        $foundDupes = [];
        foreach ($targets as $target) {
            $isV6 = Job::isIPV6($target->ip);
            $target->host_ip = $target->ip;
            $target->host_address = $isV6 ? null : $target->ip;
            $target->host_address6 = $isV6 ? $target->ip : null;

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

            unset($target->ip); // Isn't needed any more!!
            unset($target->chain); // We don't need any relation properties anymore

            $properties = iterator_to_array($target);

            $results[$target->host_name_or_ip] = (object) $properties;
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
