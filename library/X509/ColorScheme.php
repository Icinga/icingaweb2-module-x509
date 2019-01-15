<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2+

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
