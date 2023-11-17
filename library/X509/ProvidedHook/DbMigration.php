<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\ProvidedHook;

use Icinga\Application\Hook\DbMigrationHook;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Model\Schema;
use ipl\Orm\Query;
use ipl\Sql;
use ipl\Sql\Adapter\Pgsql;

class DbMigration extends DbMigrationHook
{
    public function getName(): string
    {
        return $this->translate('Icinga Certificate Monitoring');
    }

    public function providedDescriptions(): array
    {
        return [
            '1.0.0' => $this->translate(
                'Adjusts the database type of several columns and changes some composed primary keys.'
            ),
            '1.1.0' => $this->translate(
                'Changes the composed x509_target index and x509_certificate valid from/to types to bigint.'
            ),
            '1.2.0' => $this->translate(
                'Changes all timestamp columns to bigint and adjusts enum types of "yes/no" to "n/y".'
            ),
            '1.3.0' => $this->translate(
                'Introduces the required tables to store jobs and job schedules in the database.'
            )
        ];
    }

    public function getVersion(): string
    {
        if ($this->version === null) {
            $conn = $this->getDb();
            $schema = $this->getSchemaQuery()
                ->columns(['version', 'success'])
                ->orderBy('id', SORT_DESC)
                ->limit(2);

            if (static::tableExists($conn, $schema->getModel()->getTableName())) {
                /** @var Schema $version */
                foreach ($schema as $version) {
                    if ($version->success) {
                        $this->version = $version->version;

                        break;
                    }
                }

                if (! $this->version) {
                    // Schema version table exist, but the user has probably deleted the entry!
                    $this->version = '1.3.0';
                }
            } elseif (
                $this->getDb()->getAdapter() instanceof Pgsql
                || static::getColumnType($conn, 'x509_certificate', 'ctime') === 'bigint(20) unsigned'
            ) {
                // We modified a bunch of timestamp columns to bigint in x509 version 1.2.0.
                // We have also added Postgres support with x509 version 1.2 and never had an upgrade scripts until now.
                $this->version = '1.2.0';
            } elseif (static::getColumnType($conn, 'x509_certificate_subject_alt_name', 'hash') !== null) {
                if (static::getColumnType($conn, 'x509_certificate', 'valid_from') === 'bigint(20) unsigned') {
                    $this->version = '1.0.0';
                } else {
                    $this->version = '1.1.0';
                }
            } else {
                // X509 version 1.0 was the first release of this module, but due to some reason it also contains
                // an upgrade script and adds `hash` column. However, if this column doesn't exist yet, we need
                // to use the lowest possible release value as the initial (last migrated) version.
                $this->version = '0.0.0';
            }
        }

        return $this->version;
    }

    public function getDb(): Sql\Connection
    {
        return Database::get();
    }

    protected function getSchemaQuery(): Query
    {
        return Schema::on($this->getDb());
    }
}
