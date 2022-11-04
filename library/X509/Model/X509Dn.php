<?php

namespace Icinga\Module\X509\Model;

use ipl\Orm\Behavior\Binary;
use ipl\Orm\Behavior\MillisecondTimestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;

class X509Dn extends Model
{
    public function getTableName()
    {
        return 'x509_dn';
    }

    public function getTableAlias(): string
    {
        return 'dn';
    }

    public function getKeyName()
    {
        return ['hash', 'type', 'order'];
    }

    public function getColumns()
    {
        return [
            'key',
            'value',
            'ctime'
        ];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new Binary(['hash']));

        $behaviors->add(new MillisecondTimestamp(['ctime']));
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('certificate', X509Certificate::class)
            ->setForeignKey('subject_hash');
    }
}
