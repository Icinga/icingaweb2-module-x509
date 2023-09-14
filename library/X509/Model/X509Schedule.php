<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Model;

use DateTime;
use ipl\Orm\Behavior\MillisecondTimestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;

/**
 * A database model for all x509 job schedules
 *
 * @property int $id Unique identifier of this job
 * @property int $job_id The id of the x509 job this schedule belongs to
 * @property string $name The name of this job schedule
 * @property string $author The author of this job schedule
 * @property string $config The config of this job schedule
 * @property DateTime $ctime The creation time of this job
 * @property DateTime $mtime The modification time of this job
 * @property X509Job $job The x509 job this schedule belongs to
 * @property X509JobRun $job_run Schedule activities
 */
class X509Schedule extends Model
{
    public function getTableName(): string
    {
        return 'x509_schedule';
    }

    public function getTableAlias(): string
    {
        return 'schedule';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns(): array
    {
        return [
            'job_id',
            'name',
            'author',
            'config',
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
        $relations->belongsTo('job', X509Job::class)
            ->setCandidateKey('job_id');
        $relations->hasMany('job_run', X509JobRun::class)
            ->setForeignKey('schedule_id');
    }
}
