<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH

namespace Icinga\Module\X509;

use Icinga\Data\Sortable;
use ipl\Sql;

/**
 * @internal
 */
class SortAdapter implements Sortable
{
    protected $select;

    public function __construct(Sql\Select $select)
    {
        $this->select = $select;
    }

    public function order($field, $direction = null)
    {
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
