<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Common;

use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Hook\SniHook;
use Icinga\Module\X509\Job;

trait ScanUtils
{
    protected function getJob(): Job
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

        return new Job($name, $jobDescription, SniHook::getAll(), $parallel);
    }

    protected function runJob(Job $job)
    {
        $finishedTargets = $job->run();
        if (! $finishedTargets) {
            Logger::warning("The job '%s' does not have any targets.", $job->getName());
        } else {
            Logger::info(
                "Scanned %s target%s in job '%s'.\n",
                $finishedTargets,
                $finishedTargets != 1 ? 's' : '',
                $job->getName()
            );

            $verified = CertificateUtils::verifyCertificates($this->getDb());

            Logger::info("Checked %d certificate chain%s.", $verified, $verified !== 1 ? 's' : '');
        }
    }
}
