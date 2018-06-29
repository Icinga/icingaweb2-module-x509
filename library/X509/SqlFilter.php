<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH

namespace Icinga\Module\X509;

use Exception;
use ReflectionClass;
use Icinga\Data\ConfigObject;
use Icinga\Data\Db\DbConnection;
use Icinga\Data\Filter\Filter;
use ipl\Sql\Select;
use ipl\Sql\Sql;

/**
 * @internal
 */
class Quoter
{
    public function quote($value)
    {
        return Sql::quoteIdentifier($value);
    }
}

/**
 * @internal
 */
class NoImplicitConnectDbConnection extends DbConnection
{
    protected $renderFilterCallback;

    public function __construct(ConfigObject $config = null)
    {
    }

    public function setRenderFilterCallback(callable $callback = null)
    {
        $this->renderFilterCallback = $callback;

        return $this;
    }

    protected function renderFilterExpression(Filter $filter)
    {
        $hit = false;

        if (isset($this->renderFilterCallback)) {
            $hit = call_user_func($this->renderFilterCallback, $filter);
        }

        if ($hit !== false) {
            return $hit;
        }

        return parent::renderFilterExpression($filter);
    }
}

/**
 * @internal
 */
class SqlFilter
{
    public static function apply($filter, Select $select, callable $renderFilterCallback = null)
    {
        if (! $filter instanceof Filter) {
            $parts = [];

            foreach ($filter as $filterString) {
                try {
                    $parts[] = Filter::fromQueryString($filterString);
                } catch (Exception $e) {
                    continue;
                }
            }

            $filter = Filter::matchAny($parts);
        }

        if (! $filter->isEmpty()) {
            $conn = (new NoImplicitConnectDbConnection())->setRenderFilterCallback($renderFilterCallback);

            $reflection = new ReflectionClass('\Icinga\Data\Db\DbConnection');

            $dbAdapter = $reflection->getProperty('dbAdapter');
            $dbAdapter->setAccessible(true);
            $dbAdapter->setValue($conn, new Quoter());

            $select->where($conn->renderFilter($filter));
        }
    }
}
