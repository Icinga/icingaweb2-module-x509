<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Hook;

use Icinga\Application\Config;
use Icinga\Application\Hook;
use Icinga\Util\StringHelper;

/**
 * Hook for SNI maps
 */
abstract class SniHook
{
    /**
     * Return the SNI maps of all hooks
     *
     * ['192.0.2.1' => ['example.com', 'mail.example.com']]
     *
     * @return string[][]
     */
    public static function getAll()
    {
        $sni = [];

        foreach (Hook::all('X509\Sni') as $hook) {
            /** @var self $hook */
            foreach ($hook->getSniMap() as $ip => $hostnames) {
                foreach ($hostnames as $hostname) {
                    $sni[$ip][$hostname] = $hostname;
                }
            }
        }

        foreach (Config::module('x509', 'sni') as $ip => $config) {
            foreach (array_filter(StringHelper::trimSplit($config->get('hostnames', []))) as $hostname) {
                $sni[$ip][$hostname] = $hostname;
            }
        }

        return $sni;
    }

    /**
     * Return the SNI map
     *
     * ['192.0.2.1' => ['example.com', 'mail.example.com']]
     *
     * @return string[][]
     */
    abstract public function getSniMap();
}
