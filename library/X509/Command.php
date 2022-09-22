<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Application\Icinga;
use Icinga\Data\ResourceFactory;
use Icinga\Module\X509\Common\Database;
use ipl\Sql;

class Command extends \Icinga\Cli\Command
{
    use Database;

    // Fix Web 2 issue where $configs is not properly initialized
    protected $configs = [];

    public function init()
    {
        Icinga::app()->getModuleManager()->loadEnabledModules();
    }
}
