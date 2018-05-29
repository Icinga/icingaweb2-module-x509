<?php
/* Icinga Web 2 X.509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Logger;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Job;
use Icinga\Module\X509\Scheduler;

class JobsCommand extends Command
{
    /**
     * Verify all currently collected X.509 certificates
     *
     * USAGE:
     *
     *   icingacli x509 verify
     */
    public function runAction()
    {
        $parallel = (int) $this->Config()->get('scan', 'parallel', 256);

        if ($parallel <= 0) {
            $this->fail("The 'parallel' option must be set to at least 1.");
        }

        $snimap = $this->Config('sni');

        $scheduler = new Scheduler();

        $defaultSchedule = $this->Config()->get('jobs', 'default_schedule');

        $db = $this->getDb();

        foreach ($this->Config('jobs') as $name => $jobDescription) {
            $schedule = $jobDescription->get('schedule', $defaultSchedule);

            if (! $schedule) {
                Logger::debug("The job '%s' is not scheduled.", $name);
                continue;
            }

            $job = new Job($name, $db, $jobDescription, $snimap, $parallel);

            $scheduler->add($name, $schedule, function () use ($job, $name, $db) {
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
            });
        }

        $scheduler->run();
    }
}
