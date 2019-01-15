<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook\X509;

use Icinga\Application\Icinga;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Monitoring\Backend;
use Icinga\Module\X509\Hook\SniHook;
use Icinga\Module\X509\SniIniRepository;
use Icinga\Util\StringHelper;

/**
 * Load the SNI map from the module config
 */
class Sni extends SniHook
{
    public function getSniMap()
    {
        $sniMap = [];

        foreach ((new SniIniRepository())->select(['ip', 'hostnames'])->fetchAll() as $sni) {
            $hostnames = array_filter(StringHelper::trimSplit($sni->hostnames));
            $sniMap[$sni->ip] = array_combine($hostnames, $hostnames);
        }

        $modMgr = Icinga::app()->getModuleManager();

        if ($modMgr->hasEnabled('monitoring')) {
            if (! $modMgr->hasLoaded('monitoring')) {
                $modMgr->loadModule('monitoring');
            }

            foreach ($this->getSniMapFromMonitoring() as $ip => $hostnames) {
                foreach ($hostnames as $hostname) {
                    $sniMap[$ip][$hostname] = $hostname;
                }
            }
        }

        return $sniMap;
    }

    protected function getSniMapFromMonitoring()
    {
        try {
            $backend = Backend::createBackend();
        } catch (ConfigurationError $_) {
            return [];
        }

        $sniMap = [];

        foreach ($backend->select()->from('hoststatus', ['host_name', 'host_address', 'host_address6'])->fetchAll() as $host) {
            if ((string) $host->host_address !== '') {
                $sniMap[$host->host_address][$host->host_name] = $host->host_name;
            }

            if ((string) $host->host_address6 !== '') {
                $sniMap[$host->host_address6][$host->host_name] = $host->host_name;
            }
        }

        return $sniMap;
    }
}
