<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Tests\Icinga\Module\X509\Lib;

use ipl\Orm\Model;
use ipl\Sql\Expression;

class TestModel extends Model
{
    public const EXPRESSION = 'CASE WHEN 1 THEN YES ELSE NO';

    public function getTableName()
    {
        return 'test';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'duration' => new Expression(static::EXPRESSION)
        ];
    }
}
