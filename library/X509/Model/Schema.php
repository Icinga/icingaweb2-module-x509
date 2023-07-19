<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Model;

use ipl\Orm\Behavior\BoolCast;
use ipl\Orm\Behavior\MillisecondTimestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;

class Schema extends Model
{
    public function getTableName(): string
    {
        return 'x509_schema';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns(): array
    {
        return [
            'version',
            'timestamp',
            'success',
            'reason'
        ];
    }

    public function createBehaviors(Behaviors $behaviors): void
    {
        $behaviors->add(new BoolCast(['success']));
        $behaviors->add(new MillisecondTimestamp(['timestamp']));
    }
}
