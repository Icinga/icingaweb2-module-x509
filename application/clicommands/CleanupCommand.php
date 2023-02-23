<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Clicommands;

use DateTime;
use Exception;
use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Command;
use InvalidArgumentException;
use Throwable;

class CleanupCommand extends Command
{
    /**
     * Remove targets whose last scan is older than a certain date/time and certificates that are no longer used.
     *
     * By default, any targets whose last scan is older than 1 month are removed. The last scan information is
     * always updated when scanning a target, regardless of whether a successful connection is made or not.
     * Therefore, targets that have been decommissioned or are no longer part of a job configuration are removed
     * after the specified period. Any certificates that are no longer used are also removed. This can either be
     * because the associated target has been removed or because it is presenting a new certificate chain.
     *
     * USAGE
     *
     *     icingacli x509 cleanup [OPTIONS]
     *
     * OPTIONS
     *
     * --since-last-scan=<datetime>
     *     Clean up targets whose last scan is older than the specified date/time,
     *     which can also be an English textual datetime description like "2 days".
     *     Defaults to "1 month".
     *
     * EXAMPLES
     *
     * Remove any targets that have not been scanned for at least two months and any certificates that are no longer
     * used.
     *
     *     icingacli x509 cleanup --since-last-scan="2 months"
     *
     */
    public function indexAction()
    {
        $sinceLastScan = $this->params->get('since-last-scan', '-1 month');
        $lastScan = $sinceLastScan;
        if ($lastScan[0] !== '-') {
            // When the user specified "2 days" as a threshold strtotime() will compute the
            // timestamp NOW() + 2 days, but it has to be NOW() + (-2 days)
            $lastScan = "-$lastScan";
        }

        try {
            $sinceLastScan = new DateTime($lastScan);
        } catch (Exception $_) {
            throw new InvalidArgumentException(sprintf(
                'The specified last scan time is in an unknown format: %s',
                $sinceLastScan
            ));
        }

        try {
            $conn = $this->getDb();
            $query = $conn->delete(
                'x509_target',
                ['last_scan < ?' => $sinceLastScan->format('Uv')]
            );

            if ($query->rowCount() > 0) {
                Logger::info(
                    'Removed %d targets matching since last scan filter: %s',
                    $query->rowCount(),
                    $sinceLastScan->format('Y-m-d H:i:s')
                );
            }

            CertificateUtils::cleanupNoLongerUsedCertificates($conn);
        } catch (Throwable $err) {
            Logger::error($err);
        }
    }
}
