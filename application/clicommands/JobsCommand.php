<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use DateTime;
use Exception;
use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Hook\SniHook;
use Icinga\Module\X509\Job;
use ipl\Scheduler\Contract\Frequency;
use ipl\Scheduler\Contract\Task;
use ipl\Scheduler\Cron;
use ipl\Scheduler\Scheduler;
use React\EventLoop\Loop;
use React\Promise\ExtendedPromiseInterface;
use Throwable;

class JobsCommand extends Command
{
    /**
     * Run all configured jobs based on their schedule
     *
     * USAGE:
     *
     *   icingacli x509 jobs run
     */
    public function runAction()
    {
        $parallel = (int) $this->Config()->get('scan', 'parallel', 256);
        if ($parallel <= 0) {
            $this->fail("The 'parallel' option must be set to at least 1");
        }

        $scheduler = new Scheduler();
        $this->attachJobsLogging($scheduler);

        $signalHandler = function () use ($scheduler) {
            $scheduler->removeTasks();

            Loop::futureTick(function () {
                Loop::stop();
            });
        };
        Loop::addSignal(SIGINT, $signalHandler);
        Loop::addSignal(SIGTERM, $signalHandler);

        /** @var Job[] $scheduled Caches scheduled jobs */
        $scheduled = [];
        // Periodically check configuration changes to ensure that new jobs are scheduled, jobs are updated,
        // and deleted jobs are canceled.
        $watchdog = function () use (&$watchdog, $scheduler, &$scheduled, $parallel) {
            $jobs = $this->fetchJobs();
            $outdatedJobs = array_diff_key($scheduled, $jobs);
            foreach ($outdatedJobs as $job) {
                Logger::info(
                    'Removing scheduled job %s, as it either no longer exists in the configuration or its config has'
                    . ' been changed',
                    $job->getName()
                );

                $scheduler->remove($job);
            }

            $newJobs = array_diff_key($jobs, $scheduled);
            foreach ($newJobs as $job) {
                $job->setParallel($parallel);

                $config = $job->getConfig();
                if (! isset($config->frequencyType)) {
                    if (! Cron::isValid($config->schedule)) {
                        Logger::error('Job %s has invalid schedule expression %s', $job->getName(), $config->schedule);

                        continue;
                    }

                    $frequency = new Cron($config->schedule);
                } else {
                    try {
                        /** @var Frequency $type */
                        $type = $config->frequencyType;
                        $frequency = $type::fromJson($config->schedule);
                    } catch (Exception $err) {
                        Logger::error(
                            'Job %s has invalid schedule expression %s: %s',
                            $job->getName(),
                            $config->schedule,
                            $err->getMessage()
                        );

                        continue;
                    }
                }

                $scheduler->schedule($job, $frequency);
            }

            $scheduled = $jobs;

            Loop::addTimer(5 * 60, $watchdog);
        };
        // Check configuration and add jobs directly after starting the scheduler.
        Loop::futureTick($watchdog);
    }

    /**
     * Fetch jobs from disk
     *
     * @return Job[]
     */
    protected function fetchJobs(): array
    {
        $configs = Config::module($this->getModuleName(), 'jobs', true);
        $defaultSchedule = $configs->get('jobs', 'default_schedule');

        $jobs = [];
        foreach ($configs as $name => $config) {
            if (! $config->get('schedule', $defaultSchedule)) {
                Logger::debug('Job %s cannot be scheduled', $name);

                continue;
            }

            $job = new Job($name, $config, SniHook::getAll());
            $jobs[$job->getUuid()->toString()] = $job;
        }

        return $jobs;
    }

    /**
     * Set up logging of jobs states based on scheduler events
     *
     * @param Scheduler $scheduler
     */
    protected function attachJobsLogging(Scheduler $scheduler): void
    {
        $scheduler->on(Scheduler::ON_TASK_CANCEL, function (Task $job, array $_) {
            Logger::info('Job %s canceled', $job->getName());
        });

        $scheduler->on(Scheduler::ON_TASK_DONE, function (Task $job, $targets = 0) {
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
        });

        $scheduler->on(Scheduler::ON_TASK_FAILED, function (Task $job, Throwable $e) {
            Logger::error('Failed to run job %s: %s', $job->getName(), $e->getMessage());
            Logger::debug($e->getTraceAsString());
        });

        $scheduler->on(Scheduler::ON_TASK_RUN, function (Task $job, ExtendedPromiseInterface $_) {
            Logger::info('Running job %s', $job->getName());
        });

        $scheduler->on(Scheduler::ON_TASK_SCHEDULED, function (Task $job, DateTime $dateTime) {
            Logger::info('Scheduling job %s to run at %s', $job->getName(), $dateTime->format('Y-m-d H:i:s'));
        });

        $scheduler->on(Scheduler::ON_TASK_EXPIRED, function (Task $task, DateTime $dateTime) {
            Logger::info(sprintf('Detaching expired job %s at %s', $task->getName(), $dateTime->format('Y-m-d H:i:s')));
        });
    }
}
