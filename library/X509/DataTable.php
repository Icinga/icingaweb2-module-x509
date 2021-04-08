<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlString;

class DataTable extends BaseHtmlElement
{
    protected $tag = 'table';

    /**
     * Columns of the table
     *
     * @var array
     */
    protected $columns;

    /**
     * The data to display
     *
     * @var array|\Traversable
     */
    protected $data = [];

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

    protected function createColumns()
    {
    }

    public function renderHeader()
    {
        $cells = [];

        foreach ($this->columns as $column) {
            if (is_array($column)) {
                if (isset($column['label'])) {
                    $label = $column['label'];
                } else {
                    $label = new HtmlString('&nbsp;');
                }
            } else {
                $label = $column;
            }

            $cells[] = Html::tag('th', $label);
        }

        return Html::tag('thead', Html::tag('tr', $cells));
    }

    protected function renderRow($row)
    {
        $cells = [];

        foreach ($this->columns as $key => $column) {
            if (! is_int($key) && array_key_exists($key, $row)) {
                $data = $row[$key];
            } else {
                if (isset($column['column']) && array_key_exists($column['column'], $row)) {
                    $data = $row[$column['column']];
                } else {
                    $data = null;
                }
            }

            if (isset($column['renderer'])) {
                $content = call_user_func(($column['renderer']), $data, $row);
            } else {
                $content = $data;
            }

            $cells[] = Html::tag('td', isset($column['attributes']) ? $column['attributes'] : null, $content);
        }

        return Html::tag('tr', $cells);
    }

    protected function renderBody($data)
    {
        if (! is_array($data) && ! $data instanceof \Traversable) {
            throw new \InvalidArgumentException('Data must be an array or an instance of Traversable');
        }

        $rows = [];

        foreach ($data as $row) {
            $rows[] = $this->renderRow($row);
        }

        if (empty($rows)) {
            $colspan = count($this->columns);

            $rows = Html::tag(
                'tr',
                Html::tag(
                    'td',
                    ['colspan' => $colspan],
                    mt('x509', 'No results found.')
                )
            );
        }

        return Html::tag('tbody', $rows);
    }

    protected function assemble()
    {
        $this->columns = $this->createColumns();

        $this->add(array_filter([
            $this->renderHeader(),
            $this->renderBody($this->getData())
        ]));
    }
}
