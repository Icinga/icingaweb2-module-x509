<?php

namespace Icinga\Module\X509\Model;

use ipl\Orm\Model;

class X509JobRun extends Model
{
    public function getTableName()
    {
        return 'x509_job_run';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'name',
            'total_targets',
            'finished_targets',
            'start_time',
            'end_time',
            'ctime',
            'mtime'
        ];
    }
}
