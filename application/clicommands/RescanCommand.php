<?php

// Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use DateTime;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Common\ScanUtils;
use InvalidArgumentException;

class RescanCommand extends Command
{
    use ScanUtils;

    /**
     * Rescan only known IP and port combinations to track changes to their X.509 certificates.
     *
     * USAGE
     *
     * icingacli x509 rescan --job <name> [--since-last-scan <datetime>]
     *
     * OPTIONS:
     *
     *   --job=<name>                  Rescan known IP and port combinations belonging to the specified job.
     *   --since-last-scan=<datetime>  Rescans only the targets whose last scan is older than the specified
     *                                 date/time, which can also be an English textual datetime description
     *                                 like "2 days".
     */
    public function indexAction()
    {
        $sinceLastScan = $this->params->get('since-last-scan');
        if ($sinceLastScan !== null) {
            if ($sinceLastScan[0] !== '-') {
                // When the user specified "2 days" as a threshold strtotime() will compute the
                // timestamp NOW() + 2 days, but it has to be NOW() + (-2 days)
                $sinceLastScan = "-$sinceLastScan";
            }

            $timestamp = strtotime($sinceLastScan);
            if ($timestamp === false) {
                throw new InvalidArgumentException(sprintf(
                    'The specified last scan time is in an unknown format: %s',
                    $this->params->get('since-last-scan')
                ));
            }

            $sinceLastScan = new DateTime();
            $sinceLastScan->setTimestamp($timestamp);
        }

        $job = $this
            ->getJob()
            ->setRescan(true)
            ->setLastScan($sinceLastScan);

        $this->runJob($job);
    }
}
