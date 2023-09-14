<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509;

use Icinga\Module\X509\Model\X509Schedule;
use Icinga\Util\Json;
use stdClass;

class Schedule
{
    /** @var int The database id of this schedule */
    protected $id;

    /** @var string The name of this job schedule */
    protected $name;

    /** @var object The config of this schedule */
    protected $config;

    public function __construct(string $name, int $id, object $config)
    {
        $this->id = $id;
        $this->name = $name;
        $this->config = $config;
    }

    public static function fromModel(X509Schedule $schedule): self
    {
        /** @var stdClass $config */
        $config = Json::decode($schedule->config);
        if (isset($config->rescan)) {
            $config->rescan = $config->rescan === 'y';
        }

        if (isset($config->full_scan)) {
            $config->full_scan = $config->full_scan === 'y';
        }

        return new static($schedule->name, $schedule->id, $config);
    }

    /**
     * Get the name of this schedule
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the name of this schedule
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the database id of this job
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the database id of this job
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the config of this schedule
     *
     * @return object
     */
    public function getConfig(): object
    {
        return $this->config;
    }

    /**
     * Set the config of this schedule
     *
     * @param object $config
     *
     * @return $this
     */
    public function setConfig(object $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the checksum of this schedule
     *
     * @return string
     */
    public function getChecksum(): string
    {
        return md5($this->getName() . Json::encode($this->getConfig()), true);
    }
}
