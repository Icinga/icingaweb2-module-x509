<?php

// SPDX-FileCopyrightText: 2023 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
