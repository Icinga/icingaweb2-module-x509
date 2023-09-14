<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Model;

use DateTime;
use ipl\Orm\Behavior\MillisecondTimestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Query;
use ipl\Orm\Relations;

/**
 * A database model for all x509 jobs
 *
 * @property int $id Unique identifier of this job
 * @property string $name The name of this job
 * @property string $author The author of this job
 * @property string $cidrs The configured cidrs of this job
 * @property string $ports The configured ports of this job
 * @property ?string $exclude_targets The configured excluded targets of this job
 * @property DateTime $ctime The creation time of this job
 * @property DateTime $mtime The modification time of this job
 * @property Query|X509Schedule $schedule The configured schedules of this job
 * @property Query|X509JobRun $job_run Job activities
 */
class X509Job extends Model
{
    public function getTableName(): string
    {
        return 'x509_job';
    }

    public function getTableAlias(): string
    {
        return 'job';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns(): array
    {
        return [
            'name',
            'author',
            'cidrs',
            'ports',
            'exclude_targets',
            'ctime',
            'mtime'
        ];
    }

    public function createBehaviors(Behaviors $behaviors): void
    {
        $behaviors->add(new MillisecondTimestamp([
            'ctime',
            'mtime'
        ]));
    }

    public function createRelations(Relations $relations): void
    {
        $relations->hasMany('schedule', X509Schedule::class)
            ->setForeignKey('job_id');
        $relations->hasMany('job_run', X509JobRun::class)
            ->setForeignKey('job_id');
    }
}
