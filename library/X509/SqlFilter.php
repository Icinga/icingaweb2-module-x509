<?php

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
    public function __construct(ConfigObject $config = null)
    {
    }
}

/**
 * @internal
 */
class SqlFilter
{
    public static function apply($filter, Select $select)
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
            $conn = new NoImplicitConnectDbConnection();

            $reflection = new ReflectionClass('\Icinga\Data\Db\DbConnection');

            $dbAdapter = $reflection->getProperty('dbAdapter');
            $dbAdapter->setAccessible(true);
            $dbAdapter->setValue($conn, new Quoter());

            $select->where($conn->renderFilter($filter));
        }
    }
}
