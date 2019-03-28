<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Data\Sortable;
use ipl\Sql;

/**
 * @internal
 */
class SortAdapter implements Sortable
{
    protected $select;

    protected $callback;

    public function __construct(Sql\Select $select, \Closure $callback = null)
    {
        $this->select = $select;
        $this->callback = $callback;
    }

    public function order($field, $direction = null)
    {
        if ($this->callback !== null) {
            $field = call_user_func($this->callback, $field) ?: $field;
        }

        if ($direction === null) {
            $this->select->orderBy($field);
        } else {
            $this->select->orderBy($field, $direction);
        }
    }

    public function hasOrder()
    {
        return $this->select->hasOrderBy();
    }

    public function getOrder()
    {
        return $this->select->getOrderBy();
    }

}
