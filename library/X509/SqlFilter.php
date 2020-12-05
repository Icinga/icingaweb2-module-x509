<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Data\ConfigObject;
use Icinga\Data\Db\DbConnection;
use Icinga\Data\Filter\Filter;
use ipl\Sql\Connection;
use ipl\Sql\Select;

/**
 * @internal
 */
class RenderFilterCallbackDbConnection extends DbConnection
{
    protected $renderFilterCallback;

    public function setRenderFilterCallback(callable $callback = null)
    {
        $this->renderFilterCallback = $callback;

        return $this;
    }

    protected function renderFilterExpression(Filter $filter)
    {
        $hit = false;

        if (isset($this->renderFilterCallback)) {
            $hit = call_user_func($this->renderFilterCallback, clone $filter);
        }

        if ($hit !== false) {
            $filter = $hit;
        }

        return parent::renderFilterExpression($filter);
    }
}

/**
 * @internal
 */
class SqlFilter
{
    protected $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = new RenderFilterCallbackDbConnection(new ConfigObject((array) $conn->getConfig()));
    }

    public function apply(Select $select, Filter $filter = null, callable $renderFilterCallback = null)
    {
        if ($filter === null || $filter->isEmpty()) {
            return;
        }

        if (! $filter->isEmpty()) {
            $conn = clone $this->conn;
            $conn->setRenderFilterCallback($renderFilterCallback);

            $select->where($conn->renderFilter($filter));
        }
    }
}
