<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\File\Csv;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Util\Json;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Widget\Tabextension\MenuAction;
use Icinga\Web\Widget\Tabextension\OutputFormat;
use ipl\Html\Html;
use ipl\Orm\Query;
use ipl\Sql;
use ipl\Stdlib\Filter;
use ipl\Web\Compat\CompatController;
use ipl\Web\Compat\SearchControls;
use ipl\Web\Filter\QueryString;
use PDO;

class Controller extends CompatController
{
    use Database {
        getDb as private getDbWithOptions;
    }
    use SearchControls {
        SearchControls::createSearchBar as private webCreateSearchBar;
    }

    /** @var Filter\Rule */
    protected $filter;

    protected $format;

    /**
     * Get the connection to the X.509 database
     *
     * @return  Sql\Connection
     */
    protected function getDb()
    {
        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        return $this->getDbWithOptions($options);
    }

    public function fetchFilterColumns(Query $query): array
    {
        return iterator_to_array(ObjectSuggestions::collectFilterColumns($query->getModel(), $query->getResolver()));
    }

    public function getFilter(): Filter\Rule
    {
        if ($this->filter === null) {
            $this->filter = QueryString::parse((string) $this->params);
        }

        return $this->filter;
    }

    protected function handleFormatRequest(Query $query, callable $callback)
    {
        if ($this->format !== 'html' && ! $this->params->has('limit')) {
            $query->limit(null);  // Resets any default limit and offset
        }

        if ($this->format === 'sql') {
            $this->content->add(Html::tag('pre', $query->dump()[0]));
            return true;
        }

        switch ($this->format) {
            case 'json':
                $response = $this->getResponse();
                $response
                    ->setHeader('Content-Type', 'application/json')
                    ->setHeader('Cache-Control', 'no-store')
                    ->setHeader(
                        'Content-Disposition',
                        'inline; filename=' . $this->getRequest()->getActionName() . '.json'
                    )
                    ->appendBody(
                        Json::encode(iterator_to_array($callback($query)))
                    )
                    ->sendResponse();
                exit;
            case 'csv':
                $response = $this->getResponse();
                $response
                    ->setHeader('Content-Type', 'text/csv')
                    ->setHeader('Cache-Control', 'no-store')
                    ->setHeader(
                        'Content-Disposition',
                        'attachment; filename=' . $this->getRequest()->getActionName() . '.csv'
                    )
                    ->appendBody((string) Csv::fromQuery($callback($query)))
                    ->sendResponse();
                exit;
        }
    }

    public function preDispatch()
    {
        parent::preDispatch();

        $this->format = $this->params->shift('format', 'html');
    }
}
