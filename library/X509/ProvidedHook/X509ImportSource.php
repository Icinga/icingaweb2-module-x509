<?php

// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook;

use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\X509\Common\Database;
use ipl\Sql;
use PDO;

abstract class X509ImportSource extends ImportSourceHook
{
    use Database;

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
