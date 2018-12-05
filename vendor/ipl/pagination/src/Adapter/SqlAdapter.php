<?php

namespace ipl\Pagination\Adapter;

use ipl\Sql\Connection;
use ipl\Sql\Select;

/**
 * Pagination adapter for ipl SQL queries
 */
class SqlAdapter implements AdapterInterface
{
    /** @var Connection */
    protected $db;

    /** @var Select */
    protected $select;

    /**
     * Create a new SQL pagination adapter from the given connection and query
     *
     * @param   Connection  $db
     * @param   Select      $select
     */
    public function __construct(Connection $db, Select $select)
    {
        $this->db = $db;
        $this->select = $select;
    }

    public function hasLimit()
    {
        return $this->select->hasLimit();
    }

    public function getLimit()
    {
        return $this->select->getLimit();
    }

    public function limit($limit)
    {
        $this->select->limit($limit);

        return $this;
    }

    public function hasOffset()
    {
        return $this->select->hasOffset();
    }

    public function getOffset()
    {
        return $this->select->getOffset();
    }

    public function offset($offset)
    {
        $this->select->offset($offset);

        return $this;
    }

    public function count()
    {
        return $this->db->select($this->select->getCountQuery())->fetchColumn(0);
    }
}
