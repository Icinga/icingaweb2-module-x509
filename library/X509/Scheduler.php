<?php

namespace Icinga\Module\X509;

use Cron\CronExpression;
use Icinga\Application\Logger;
use React\EventLoop\Factory as Loop;

class Scheduler
{
    protected $loop;

    public function __construct()
    {
        $this->loop = Loop::create();
    }

    public function add($name, $cronSchedule, callable $callback)
    {
        if (! CronExpression::isValidExpression($cronSchedule)) {
            throw new \RuntimeException('Invalid cron expression');
        }

        $now = new \DateTime();

        $expression = CronExpression::factory($cronSchedule);

        $nextRuns = $expression->getMultipleRunDates(2, $now);

        $interval = $nextRuns[0]->getTimestamp() - $now->getTimestamp();

        $period = $nextRuns[1]->getTimestamp() - $nextRuns[0]->getTimestamp();

        Logger::info('Scheduling job %s to run at %s.', $name, $nextRuns[0]->format('Y-m-d H:i:s'));

        $loop = function () use (&$loop, $name, $callback, $period) {
            $callback();

            $nextRun = (new \DateTime())
                ->add(new \DateInterval("PT{$period}S"));

            Logger::info('Scheduling job %s to run at %s.', $name, $nextRun->format('Y-m-d H:i:s'));

            $this->loop->addTimer($period, $loop);
        };

        $this->loop->addTimer($interval, $loop);
    }

    public function run()
    {
        $this->loop->run();
    }
}
