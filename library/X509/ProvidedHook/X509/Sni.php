<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook\X509;

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
            $sniMap[$sni->ip] = array_filter(StringHelper::trimSplit($sni->hostnames));
        }

        return $sniMap;
    }
}
