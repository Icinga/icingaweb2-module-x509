<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook;

use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Director\Hook\ImportSourceHook;
use ipl\Sql;
use PDO;

abstract class x509ImportSource extends ImportSourceHook
{
    /**
     * Get the connection to the X.509 database
     *
     * @return  Sql\Connection
     */
    protected function getDb()
    {
        $config = new Sql\Config(ResourceFactory::getResourceConfig(
            Config::module('x509')->get('backend', 'resource')
        ));
        $config->options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ];

        $conn = new Sql\Connection($config);

        return $conn;
    }

    /**
     * Transform the given binary IP address in a human readable format
     *
     * @param   string  $ip
     *
     * @return  array   The first element is IPv4, the second IPv6
     */
    protected function transformIpAddress($ip)
    {
        $ipv4 = ltrim($ip, "\0");
        if (strlen($ipv4) === 4) {
            return [inet_ntop($ipv4), null];
        } else {
            return [null, inet_ntop($ip)];
        }
    }
}
