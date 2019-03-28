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
        // This is implemented as map of maps to avoid duplicates,
        // the caller is expected to handle it as map of sequences though
        $sni = [];

        foreach (Hook::all('X509\Sni') as $hook) {
            /** @var self $hook */
            foreach ($hook->getHosts() as $ip => $hostname) {
                $sni[$ip][$hostname] = $hostname;
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
     * Aggregate pairs of ip => hostname
     *
     * @return \Generator
     */
    abstract public function getHosts();
}
