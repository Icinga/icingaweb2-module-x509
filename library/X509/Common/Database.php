<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Common;

use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use ipl\Sql;
use PDO;

final class Database
{
    /** @var Sql\Connection Database connection */
    private static $instance;

    private function __construct()
    {
    }

    /**
     * Get the database connection
     *
     * @return Sql\Connection
     */
    public static function get(): Sql\Connection
    {
        if (self::$instance === null) {
            self::$instance = self::getDb();
        }

        return self::$instance;
    }

    /**
     * Get the connection to the X.509 database
     *
     * @return  Sql\Connection
     */
    private static function getDb(): Sql\Connection
    {
        $config = new Sql\Config(ResourceFactory::getResourceConfig(
            Config::module('x509')->get('backend', 'resource', 'x509')
        ));

        $options = [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ];
        if ($config->db === 'mysql') {
            // In PHP 8.5+, driver-specific constants of the PDO class are deprecated,
            // but the replacements are only available since php 8.4
            if (version_compare(PHP_VERSION, '8.4.0', '<')) {
                $mysqlAttrInitCommand = PDO::MYSQL_ATTR_INIT_COMMAND;
            } else {
                $mysqlAttrInitCommand = Pdo\Mysql::ATTR_INIT_COMMAND;
            }

            $options[$mysqlAttrInitCommand] = "SET SESSION SQL_MODE='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE"
                . ",NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'";
        }

        $config->options = $options;

        return new Sql\Connection($config);
    }
}
