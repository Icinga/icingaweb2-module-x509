<?php

// SPDX-FileCopyrightText: 2023 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Modules\X509\Model\Behavior;

use Icinga\Module\X509\Model\Behavior\ExpressionInjector;
use ipl\Orm\Query;
use ipl\Sql\Connection;
use ipl\Stdlib\Filter\Equal;
use PHPUnit\Framework\TestCase;
use Tests\Icinga\Module\X509\Lib\TestModel;

class ExpressionInjectorTest extends TestCase
{
    public function testRewriteConditionReplacesExpressionColumnByItsExpression()
    {
        $cond = new Equal('duration', 'FOOO');
        $cond->metaData()->set('columnName', 'duration');
        $this->assertSame('duration', $cond->getColumn());
        $this->assertSame('FOOO', $cond->getValue());

        $this->behavior()->rewriteCondition($cond);

        $this->assertSame('FOOO', $cond->getValue());
        $this->assertSame(TestModel::EXPRESSION, $cond->getColumn());
    }

    protected function behavior(): ExpressionInjector
    {
        return (new ExpressionInjector('duration'))
            ->setQuery(
                (new Query())
                    ->setDb(new Connection(['db' => 'mysql']))
                    ->setModel(new TestModel())
            );
    }
}
