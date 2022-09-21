<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Hook\SniHook;
use Icinga\Module\X509\Job;

class ScanCommand extends Command
{
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
        $name = $this->params->shiftRequired('job');

        $parallel = (int) $this->Config()->get('scan', 'parallel', 256);

        if ($parallel <= 0) {
            $this->fail("The 'parallel' option must be set to at least 1.");
        }

        $jobs = $this->Config('jobs');

        if (! $jobs->hasSection($name)) {
            $this->fail('Job not found.');
        }

        $jobDescription = $this->Config('jobs')->getSection($name);

        if (! strlen($jobDescription->get('cidrs'))) {
            $this->fail('The job does not specify any CIDRs.');
        }

        $db = $this->getDb();

        $job = new Job($name, $db, $jobDescription, SniHook::getAll(), $parallel);

        $finishedTargets = $job->run();

        if ($finishedTargets === null) {
            Logger::warning("The job '%s' does not have any targets.", $name);
        } else {
            Logger::info(
                "Scanned %s target%s in job '%s'.\n",
                $finishedTargets,
                $finishedTargets != 1 ? 's' : '',
                $name
            );

            $verified = CertificateUtils::verifyCertificates($db);

            Logger::info("Checked %d certificate chain%s.", $verified, $verified !== 1 ? 's' : '');
        }
    }
}
