<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Common;

use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use ipl\Sql;
use PDO;

trait Database
{
    /**
     * Get the connection to the X.509 database
     *
     * @return  Sql\Connection
     */
    protected function getDb(array $options = [])
    {
        $config = new Sql\Config(ResourceFactory::getResourceConfig(
            Config::module('x509')->get('backend', 'resource')
        ));

        if (! isset($options[PDO::ATTR_DEFAULT_FETCH_MODE])) {
            $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_OBJ;
        }

        if ($config->db === 'mysql') {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET SESSION SQL_MODE='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE"
                . ",NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'";
        }

        $config->options = $options;

        return new Sql\Connection($config);
    }
}
