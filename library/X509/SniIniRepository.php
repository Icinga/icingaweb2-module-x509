<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Repository\IniRepository;

/**
 * Collection of hostnames stored in the sni.ini file
 */
class SniIniRepository extends IniRepository
{
    protected $queryColumns = array('sni' => array('ip', 'hostnames'));

    protected $configs = array('sni' => array(
        'module'    => 'x509',
        'name'      => 'sni',
        'keyColumn' => 'ip'
    ));
}
