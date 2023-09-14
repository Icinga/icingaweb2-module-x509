<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Model;

use DateTime;
use ipl\Orm\Behavior\MillisecondTimestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Query;
use ipl\Orm\Relations;

/**
 * A database model for all x509 job schedules
 *
 * @property int $id Unique identifier of this job
 * @property ?int $job_id The id of the x509 job this job run belongs to
 * @property ?int $schedule_id The id of the x509 job schedule this run belongs to
 * @property int $total_targets All the x509 targets found by this job run
 * @property int $finished_targets All the x509 targets scanned by this job run
 * @property DateTime $start_time The start time of this job run
 * @property DateTime $end_time The end time of this job run
 * @property Query|X509Job $job The x509 job this job run belongs to
 * @property Query|X509Schedule $schedule The x509 job schedule this job run belongs to
 */
class X509JobRun extends Model
{
    public function getTableName(): string
    {
        return 'x509_job_run';
    }

    public function getTableAlias(): string
    {
        return 'job_run';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns(): array
    {
        return [
            'job_id',
            'schedule_id',
            'total_targets',
            'finished_targets',
            'start_time',
            'end_time'
        ];
    }

    public function getDefaultSort(): string
    {
        return 'start_time desc';
    }

    public function createBehaviors(Behaviors $behaviors): void
    {
        $behaviors->add(new MillisecondTimestamp([
            'start_time',
            'end_time',
        ]));
    }

    public function createRelations(Relations $relations): void
    {
        $relations->belongsTo('job', X509Job::class)
            ->setCandidateKey('job_id');
        $relations->belongsTo('schedule', X509Schedule::class)
            ->setJoinType('LEFT')
            ->setCandidateKey('schedule_id');
    }
}
