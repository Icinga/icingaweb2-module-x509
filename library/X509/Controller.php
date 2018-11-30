<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Data\ResourceFactory;
use Icinga\File\Csv;
use Icinga\Util\Json;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Widget\Tabextension\MenuAction;
use Icinga\Web\Widget\Tabextension\OutputFormat;
use ipl\Sql;
use PDO;

class Controller extends \Icinga\Web\Controller
{
    /**
     * Get the connection to the X.509 database
     *
     * @return  Sql\Connection
     */
    protected function getDb()
    {
        $config = new Sql\Config(ResourceFactory::getResourceConfig(
            $this->Config()->get('backend', 'resource')
        ));

        $config->options = [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

        $conn = new Sql\Connection($config);

        return $conn;
    }

    /**
     * Set the title tab of this view
     *
     * @param   string  $label
     *
     * @return  $this
     */
    protected function setTitle($label)
    {
        $this->getTabs()->add(uniqid(), [
            'active'    => true,
            'label'     => (string) $label,
            'url'       => $this->getRequest()->getUrl()
        ]);

        return $this;
    }

    protected function handleFormatRequest(Sql\Connection $db, Sql\Select $select, callable $callback = null)
    {
        $desiredContentType = $this->getRequest()->getHeader('Accept');
        if ($desiredContentType === 'application/json') {
            $desiredFormat = 'json';
        } elseif ($desiredContentType === 'text/csv') {
            $desiredFormat = 'csv';
        } else {
            $desiredFormat = strtolower($this->params->get('format', 'html'));
        }

        if ($desiredFormat !== 'html' && ! $this->params->has('limit')) {
            $select->limit(null);  // Resets any default limit and offset
        }

        switch ($desiredFormat) {
            case 'sql':
                echo '<pre>'
                    . var_export((new Sql\QueryBuilder())->assembleSelect($select), true)
                    . '</pre>';
                exit;
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
                        Json::encode($callback !== null ? iterator_to_array($callback($db->select($select))) : $db->select($select)->fetchAll())
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
                    ->appendBody(
                        (string) Csv::fromQuery($callback !== null ? $callback($db->select($select)) : $db->select($select))
                    )
                    ->sendResponse();
                exit;
        }
    }

    protected function initTabs()
    {
        $this->getTabs()->extend(new OutputFormat())->extend(new DashboardAction())->extend(new MenuAction());

        return $this;
    }
}
