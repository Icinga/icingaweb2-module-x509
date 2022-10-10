<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Common\ScanUtils;
use Icinga\Module\X509\Hook\SniHook;
use Icinga\Module\X509\Job;

class ScanCommand extends Command
{
    use ScanUtils;

    /**
     * Scans IP and port ranges to find X.509 certificates.
     *
     * This command starts scanning the IP and port ranges which belong to the job that was specified with the
     * --job parameter.
     *
     * USAGE
     *
     * icingacli x509 scan --job <name>
     */
    public function indexAction()
    {
        $this->runJob($this->getJob());
    }
}
