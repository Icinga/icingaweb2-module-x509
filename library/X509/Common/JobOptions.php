<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Common;

use DateTime;
use Exception;
use Icinga\Module\X509\Job;
use Icinga\Module\X509\Schedule;
use InvalidArgumentException;
use LogicException;
use stdClass;

trait JobOptions
{
    /** @var bool Whether this job should only perform a rescan */
    protected $rescan;

    /** @var bool Whether this job should perform a full scan */
    protected $fullScan;

    /** @var ?string Since last scan threshold used to filter out scan targets */
    protected $sinceLastScan;

    /** @var int Used to control how many targets can be scanned in parallel */
    protected $parallel = Job::DEFAULT_PARALLEL;

    /** @var Schedule The job schedule config */
    protected $schedule;

    /**
     * Get whether this job is performing only a rescan
     *
     * @return bool
     */
    public function isRescan(): bool
    {
        return $this->rescan;
    }

    /**
     * Set whether this job should do only a rescan or full scan
     *
     * @param bool $rescan
     *
     * @return $this
     */
    public function setRescan(bool $rescan): self
    {
        $this->rescan = $rescan;

        return $this;
    }

    public function getParallel(): int
    {
        return $this->parallel;
    }

    public function setParallel(int $parallel): self
    {
        $this->parallel = $parallel;

        return $this;
    }

    /**
     * Set whether this job should scan all known and unknown targets
     *
     * @param bool $fullScan
     *
     * @return $this
     */
    public function setFullScan(bool $fullScan): self
    {
        $this->fullScan = $fullScan;

        return $this;
    }

    /**
     * Set since last scan threshold for the targets to rescan
     *
     * @param ?string $time
     *
     * @return $this
     */
    public function setLastScan(?string $time): self
    {
        if ($time && $time !== 'null') {
            $sinceLastScan = $time;
            if ($sinceLastScan[0] !== '-') {
                // When the user specified "2 days" as a threshold strtotime() will compute the
                // timestamp NOW() + 2 days, but it has to be NOW() + (-2 days)
                $sinceLastScan = "-$sinceLastScan";
            }

            try {
                // Ensure it's a valid date time string representation.
                new DateTime($sinceLastScan);

                $this->sinceLastScan = $sinceLastScan;
            } catch (Exception $_) {
                throw new InvalidArgumentException(sprintf(
                    'The specified last scan time is in an unknown format: %s',
                    $time
                ));
            }
        }

        return $this;
    }

    /**
     * Get the targets since last scan threshold
     *
     * @return ?DateTime
     */
    public function getSinceLastScan(): ?DateTime
    {
        if (! $this->sinceLastScan) {
            return null;
        }

        return new DateTime($this->sinceLastScan);
    }

    /**
     * Get the schedule config of this job
     *
     * @return Schedule
     */
    public function getSchedule(): Schedule
    {
        if (! $this->schedule) {
            throw new LogicException('You are accessing an unset property. Please make sure to set it beforehand.');
        }

        return $this->schedule;
    }

    /**
     * Set the schedule config of this job
     *
     * @param Schedule $schedule
     *
     * @return $this
     */
    public function setSchedule(Schedule $schedule): self
    {
        $this->schedule = $schedule;

        /** @var stdClass $config */
        $config = $schedule->getConfig();
        $this->setFullScan($config->full_scan ?? false);
        $this->setRescan($config->rescan ?? false);
        $this->setLastScan($config->since_last_scan ?? Job::DEFAULT_SINCE_LAST_SCAN);

        return $this;
    }
}
