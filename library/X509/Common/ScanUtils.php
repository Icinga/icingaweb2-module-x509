<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Common;

use Exception;
use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Hook\SniHook;
use Icinga\Module\X509\Job;
use React\EventLoop\Loop;
use Throwable;

trait ScanUtils
{
    protected function getJob(): Job
    {
        $name = $this->params->shiftRequired('job');

        $parallel = (int) $this->Config()->get('scan', 'parallel', 256);
        if ($parallel <= 0) {
            throw new Exception('The \'parallel\' option must be set to at least 1');
        }

        $jobs = $this->Config('jobs');
        if (! $jobs->hasSection($name)) {
            throw new Exception(sprintf('Job %s not found', $name));
        }

        $jobDescription = $this->Config('jobs')->getSection($name);
        if (! strlen($jobDescription->get('cidrs'))) {
            throw new Exception(sprintf('The job %s does not specify any CIDRs', $name));
        }

        return (new Job($name, $jobDescription, SniHook::getAll()))
            ->setParallel($parallel);
    }

    protected function runJob(Job $job)
    {
        $promise = $job->run();
        $signalHandler = function () use (&$promise, $job) {
            $promise->cancel();

            Logger::info('Job %s canceled', $job->getName());

            Loop::futureTick(function () {
                Loop::stop();
            });
        };
        Loop::addSignal(SIGINT, $signalHandler);
        Loop::addSignal(SIGTERM, $signalHandler);

        $promise->then(function ($targets = 0) use ($job) {
            if ($targets === 0) {
                Logger::warning('The job %s does not have any targets', $job->getName());
            } else {
                Logger::info('Scanned %d target(s) from job %s', $targets, $job->getName());

                try {
                    $verified = CertificateUtils::verifyCertificates($this->getDb());

                    Logger::info('Checked %d certificate chain(s)', $verified);
                } catch (Exception $err) {
                    Logger::error($err->getMessage());
                    Logger::debug($err->getTraceAsString());
                }
            }
        }, function (Throwable $err) use ($job) {
            Logger::error('Failed to run job %s: %s', $job->getName(), $err->getMessage());
            Logger::debug($err->getTraceAsString());
        })->always(function () {
            Loop::futureTick(function () {
                Loop::stop();
            });
        });
    }
}
