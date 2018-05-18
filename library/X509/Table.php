<?php

namespace Icinga\Module\X509;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\Text;
use ipl\Translation\Translation;

class Table extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'table';

    /**
     * Columns of the table
     *
     * @var array
     */
    protected $columns;

    /**
     * The X.509 certificates to display
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
                    $label = new Text('&nbsp;', true);
                }
            } else {
                $label = $column;
            }

            $cells[] = Html::content('th', $label);
        }

        return Html::content('thead', Html::content('tr', $cells));
    }

    protected function renderRow($row)
    {
        $cells = [];

        foreach ($this->columns as $key => $column) {
            if (array_key_exists($key, $row)) {
                $data = $row[$key];
            } else {
                if (isset($column['column']) && array_key_exists($column['column'], $row)) {
                    $data = $row[$column['column']];
                } else {
                    throw new \UnexpectedValueException();
                }
            }

            if (isset($column['renderer'])) {
                $content = call_user_func(($column['renderer']), $data, $row);
            } else {
                $content = $data;
            }

            $cells[] = Html::tag('td', isset($column['attributes']) ? $column['attributes'] : null, $content);
        }

        return Html::content('tr', $cells);
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

            $rows = Html::content('tr',
                Html::tag(
                    'td',
                    ['colspan' => $colspan],
                    $this->translate('No results found.')
                )
            );
        }

        return Html::content('tbody', $rows);
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
