<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filterable;

/**
 * @internal
 */
class FilterAdapter implements Filterable
{
    /**
     * @var Filter
     */
    protected $filter;

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
            if ($this->filter === null) {
                $this->filter = $filter;
            } else {
                $this->filter->andFilter($filter);
            }
        }

        return $this;
    }

    public function where($condition, $value = null)
    {
        $this->addFilter(Filter::expression($condition, '=', $value));

        return $this;
    }
}
