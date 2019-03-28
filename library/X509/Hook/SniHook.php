<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Hook;

use Icinga\Application\Hook;

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
            if ($hook instanceof self) {
                foreach ($hook->getSniMap() as $ip => $hostnames) {
                    foreach ($hostnames as $hostname) {
                        $sni[$ip][$hostname] = $hostname;
                    }
                }
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
