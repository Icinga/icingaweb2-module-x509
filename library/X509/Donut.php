<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use ipl\Html\Text;

class Donut extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'cert-donut'];

    /**
     * The donut data
     *
     * @var array|\Traversable
     */
    protected $data = [];

    protected $heading;

    protected $headingLevel;

    protected $labelCallback;

    /**
     * Get data to display
     *
     * @return  array|\Traversable
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the data to display
     *
     * @param   array|\Traversable  $data
     *
     * @return  $this
     */
    public function setData($data)
    {
        if (! is_array($data) && ! $data instanceof \Traversable) {
            throw new \InvalidArgumentException('Data must be an array or an instance of Traversable');
        }

        $this->data = $data;

        return $this;
    }

    public function setHeading($heading, $level)
    {
        $this->heading = $heading;
        $this->headingLevel = (int) $level;

        return $this;
    }

    public function setLabelCallback(callable $callback)
    {
        $this->labelCallback = $callback;

        return $this;
    }

    public function assemble()
    {
        $palette = ['#FF595E', '#FFCA3A', '#8AC926', '#1982C4', '#6A4C93'];

        $colorScheme = (new ColorScheme($palette))->scheme();
        $donut = new \Icinga\Chart\Donut();
        $legend = new Table();

        foreach ($this->data as $data) {
            $color = $colorScheme();
            $donut->addSlice((int) $data['cnt'], ['stroke' => $color]);
            $legend->addRow(
                [
                    Html::tag('span', ['class' => 'badge', 'style' => "background-color: $color; height: 1.75em;"]),
                    call_user_func($this->labelCallback, $data),
                    $data['cnt']
                ]
            );
        }

        $this->add([Html::tag("h{$this->headingLevel}", $this->heading), new HtmlString($donut->render()), $legend]);
    }
}
