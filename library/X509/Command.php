<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Application\Icinga;

class Command extends \Icinga\Cli\Command
{
    // Fix Web 2 issue where $configs is not properly initialized
    protected $configs = [];

    public function init()
    {
        Icinga::app()->getModuleManager()->loadEnabledModules();
    }
}
