<?php

namespace Icinga\Module\X509\Model\Behavior;

use ipl\Orm\Contract\QueryAwareBehavior;
use ipl\Orm\Contract\RewriteFilterBehavior;
use ipl\Orm\Query;
use ipl\Stdlib\Filter;

/**
 * Support expression columns (which don't really exist in the database, but rather
 * resulted e.g. from a `case..when` expression), being used as filter columns
 */
class ExpressionInjector implements RewriteFilterBehavior, QueryAwareBehavior
{
    /** @var array */
    protected $columns;

    /** @var Query */
    protected $query;

    public function __construct(...$columns)
    {
        $this->columns = $columns;
    }

    public function setQuery(Query $query)
    {
        $this->query = $query;

        return $this;
    }

    public function rewriteCondition(Filter\Condition $condition, $relation = null)
    {
        $columnName = $condition->metaData()->get('columnName');
        if (in_array($columnName, $this->columns, true)) {
            $relationPath = $condition->metaData()->get('relationPath');
            if ($relationPath && $relationPath !== $this->query->getModel()->getTableAlias()) {
                $subject = $this->query->getResolver()->resolveRelation($relationPath)->getTarget();
            } else {
                $subject = $this->query->getModel();
            }

            $expression = clone $subject->getColumns()[$columnName];
            $expression->setColumns($this->query->getResolver()->qualifyColumns(
                $this->query->getResolver()->requireAndResolveColumns(
                    $expression->getColumns(),
                    $subject
                ),
                $subject
            ));

            $condition->setColumn($this->query->getDb()->getQueryBuilder()->buildExpression($expression));
        }
    }
}
