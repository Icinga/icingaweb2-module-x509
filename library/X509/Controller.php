<?php
/* Icinga Web 2 X.509 Module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509;

use Icinga\Data\ResourceFactory;
use ipl\Sql;

class Controller extends \Icinga\Web\Controller
{
    /**
     * Get the connection to the X.509 database
     *
     * @return  Sql\Connection
     */
    public function getDb()
    {
        $config = new Sql\Config(ResourceFactory::getResourceConfig(
            $this->Config()->get('backend', 'resource')
        ));

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
    public function setTitle($label)
    {
        $this->getTabs()->add(uniqid(), [
            'active'    => true,
            'label'     => (string) $label,
            'url'       => $this->getRequest()->getUrl()
        ]);

        return $this;
    }
}
