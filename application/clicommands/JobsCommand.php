<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use DateTime;
use Exception;
use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Data\ResourceFactory;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Common\JobUtils;
use Icinga\Module\X509\Hook\SniHook;
use Icinga\Module\X509\Job;
use Icinga\Module\X509\Model\X509Job;
use Icinga\Module\X509\Model\X509Schedule;
use Icinga\Module\X509\Schedule;
use InvalidArgumentException;
use ipl\Orm\Query;
use ipl\Scheduler\Contract\Frequency;
use ipl\Scheduler\Scheduler;
use ipl\Stdlib\Filter;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Loop;
use React\Promise\ExtendedPromiseInterface;
use stdClass;
use Throwable;

class JobsCommand extends Command
{
    use JobUtils;

    /**
     * Run all configured jobs based on their schedule
     *
     * USAGE:
     *
     *     icingacli x509 jobs run [OPTIONS]
     *
     * OPTIONS
     *
     * --job=<name>
     *     Run all configured schedules only of the specified job.
     *
     * --schedule=<name>
     *     Run only the given schedule of the specified job. Providing a schedule name
     *     without a job will fail immediately.
     *
     * --parallel=<number>
     *     Allow parallel scanning of targets up to the specified number. Defaults to 256.
     *     May cause **too many open files** error if set to a number higher than the configured one (ulimit).
     */
    public function runAction(): void
    {
        $parallel = (int) $this->params->get('parallel', Job::DEFAULT_PARALLEL);
        if ($parallel <= 0) {
            $this->fail("The 'parallel' option must be set to at least 1");
        }

        $jobName = (string) $this->params->get('job');
        $scheduleName = (string) $this->params->get('schedule');
        if (! $jobName && $scheduleName) {
            throw new InvalidArgumentException('You cannot provide a schedule without a job');
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
        $watchdog = function () use (&$watchdog, &$scheduled, $scheduler, $parallel, $jobName, $scheduleName) {
            $jobs = [];
            try {
                // Since this is a long-running daemon, the resources or module config may change meanwhile.
                // Therefore, reload the resources and module config from disk each time (at 5m intervals)
                // before reconnecting to the database.
                ResourceFactory::setConfig(Config::app('resources', true));
                Config::module('x509', 'config', true);

                $jobs = $this->fetchSchedules($jobName, $scheduleName);
            } catch (Throwable $err) {
                Logger::error('Failed to fetch job schedules from the database: %s', $err);
                Logger::debug($err->getTraceAsString());
            }

            $outdatedJobs = array_diff_key($scheduled, $jobs);
            foreach ($outdatedJobs as $job) {
                Logger::info(
                    'Removing schedule %s of job %s, as it either no longer exists in the configuration or its'
                    . ' config has been changed',
                    $job->getSchedule()->getName(),
                    $job->getName()
                );

                $scheduler->remove($job);
            }

            $newJobs = array_diff_key($jobs, $scheduled);
            foreach ($newJobs as $job) {
                $job->setParallel($parallel);

                /** @var stdClass $config */
                $config = $job->getSchedule()->getConfig();
                try {
                    /** @var Frequency $type */
                    $type = $config->type;
                    $frequency = $type::fromJson($config->frequency);
                } catch (Throwable $err) {
                    Logger::error(
                        'Cannot create schedule %s of job %s: %s',
                        $job->getSchedule()->getName(),
                        $job->getName(),
                        $err->getMessage()
                    );

                    continue;
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
     * Fetch job schedules from database
     *
     * @param ?string $jobName
     * @param ?string $scheduleName
     *
     * @return Job[]
     */
    protected function fetchSchedules(?string $jobName, ?string $scheduleName): array
    {
        $jobs = X509Job::on($this->getDb());
        if ($jobName) {
            $jobs->filter(Filter::equal('name', $jobName));
        }

        $jobSchedules = [];
        $snimap = SniHook::getAll();
        /** @var X509Job $jobConfig */
        foreach ($jobs as $jobConfig) {
            $cidrs = $this->parseCIDRs($jobConfig->cidrs);
            $ports = $this->parsePorts($jobConfig->ports);
            $job = (new Job($jobConfig->name, $cidrs, $ports, $snimap))
                ->setId($jobConfig->id)
                ->setExcludes($this->parseExcludes($jobConfig->exclude_targets));

            /** @var Query $schedules */
            $schedules = $jobConfig->schedule;
            if ($scheduleName) {
                $schedules->filter(Filter::equal('name', $scheduleName));
            }

            $jobSchedules = [];
            /** @var X509Schedule $scheduleModel */
            foreach ($schedules as $scheduleModel) {
                $schedule = Schedule::fromModel($scheduleModel);
                $job = (clone $job)
                    ->setSchedule($schedule)
                    ->setUuid(Uuid::fromBytes($job->getChecksum()));

                $jobSchedules[$job->getUuid()->toString()] = $job;
            }

            if (! isset($jobSchedules[$job->getUuid()->toString()])) {
                Logger::info('Skipping job %s because no schedules are configured', $job->getName());
            }
        }

        return $jobSchedules;
    }

    /**
     * Set up logging of jobs states based on scheduler events
     *
     * @param Scheduler $scheduler
     */
    protected function attachJobsLogging(Scheduler $scheduler): void
    {
        $scheduler->on(Scheduler::ON_TASK_CANCEL, function (Job $task, array $_) {
            Logger::info('Schedule %s of job %s canceled', $task->getSchedule()->getName(), $task->getName());
        });

        $scheduler->on(Scheduler::ON_TASK_DONE, function (Job $task, $targets = 0) {
            if ($targets === 0) {
                $sinceLastScan = $task->getSinceLastScan();
                if ($sinceLastScan) {
                     Logger::info(
                         'Schedule %s of job %s does not have any targets to be rescanned matching since last scan: %s',
                         $task->getSchedule()->getName(),
                         $task->getName(),
                         $sinceLastScan->format('Y-m-d H:i:s')
                     );
                } else {
                    Logger::warning(
                        'Schedule %s of job %s does not have any targets',
                        $task->getSchedule()->getName(),
                        $task->getName()
                    );
                }
            } else {
                Logger::info(
                    'Scanned %d target(s) by schedule %s of job %s',
                    $targets,
                    $task->getSchedule()->getName(),
                    $task->getName()
                );

                try {
                    $verified = CertificateUtils::verifyCertificates($this->getDb());

                    Logger::info('Checked %d certificate chain(s)', $verified);
                } catch (Exception $err) {
                    Logger::error($err->getMessage());
                    Logger::debug($err->getTraceAsString());
                }
            }
        });

        $scheduler->on(Scheduler::ON_TASK_FAILED, function (Job $task, Throwable $e) {
            Logger::error(
                'Failed to run schedule %s of job %s: %s',
                $task->getSchedule()->getName(),
                $task->getName(),
                $e->getMessage()
            );
            Logger::debug($e->getTraceAsString());
        });

        $scheduler->on(Scheduler::ON_TASK_RUN, function (Job $task, ExtendedPromiseInterface $_) {
            Logger::info('Running schedule %s of job %s', $task->getSchedule()->getName(), $task->getName());
        });

        $scheduler->on(Scheduler::ON_TASK_SCHEDULED, function (Job $task, DateTime $dateTime) {
            Logger::info(
                'Scheduling %s of job %s to run at %s',
                $task->getSchedule()->getName(),
                $task->getName(),
                $dateTime->format('Y-m-d H:i:s')
            );
        });

        $scheduler->on(Scheduler::ON_TASK_EXPIRED, function (Job $task, DateTime $dateTime) {
            Logger::info(
                'Detaching expired schedule %s of job %s at %s',
                $task->getSchedule()->getName(),
                $task->getName(),
                $dateTime->format('Y-m-d H:i:s')
            );
        });
    }
}
