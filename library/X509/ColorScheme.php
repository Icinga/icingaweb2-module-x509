<?php

namespace Icinga\Module\X509;

use ArrayIterator;
use InfiniteIterator;

class ColorScheme
{
    /**
     * The colors of this scheme
     *
     * @var array
     */
    protected $colors;

    public function __construct(array $colors)
    {
        $this->colors = $colors;
    }

    public function scheme()
    {
        $iter = new InfiniteIterator(new ArrayIterator($this->colors));
        $iter->rewind();

        return function () use ($iter) {
            $color = $iter->current();

            $iter->next();

            return $color;
        };
    }
}
