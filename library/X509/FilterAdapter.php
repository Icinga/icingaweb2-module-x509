<?php

namespace Icinga\Module\X509;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filterable;

/**
 * @internal
 */
class FilterAdapter implements Filterable
{
    protected $filter;

    public function __construct()
    {
        $this->filter = Filter::matchAll();
    }

    public function applyFilter(Filter $filter)
    {
        return $this->addFilter($filter);
    }

    public function setFilter(Filter $filter)
    {
        $this->filter = $filter;

        return $this;
    }

    public function getFilter()
    {
        return $this->filter;
    }

    public function addFilter(Filter $filter)
    {
        if (! $filter->isEmpty()) {
            $this->filter->addFilter($filter);
        }

        return $this;
    }

    public function where($condition, $value = null)
    {
        $this->filter->addFilter(Filter::expression($condition, '=', $value));

        return $this;
    }
}
