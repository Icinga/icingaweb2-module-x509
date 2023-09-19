# Upgrading Icinga Certificate Monitoring

Upgrading Icinga Certificate Monitoring is straightforward.
Usually the only manual steps involved are schema updates for the database.

## Upgrading to version 1.3.0

Icinga Certificate Monitoring version `1.3.0` requires a schema update for the database. We have dropped the use of **INI**
files to store jobs and are using the database instead. So you need to migrate your job configs to the database.

If you're already using Icinga Web 2 version `>= 2.12`, then you don't need to import the sql upgrade scripts manually.
Icinga Web provides you the ability to perform such migrations in a simple way. You may be familiar with such an automation
if you're an Icinga Director user.

> **Note**
> 
> Please note that it doesn't matter if you import the database upgrade script manually or via the new automation,
> you will have to migrate your [Jobs config](#migrate-jobs) from INI to the database manually afterwards.

Before migrating your jobs from **INI** to the database, you need to first apply the migration script. This will create
the tables needed to store the jobs and schedules in the database.

You may use the following command to apply the database schema upgrade file:
<!-- {% if not icingaDocs %} -->

**Note:** If you haven't installed this module from packages, then please adapt the schema path to the correct installation path.

<!-- {% endif %} -->
```sql
# mysql -u root -p x509 < /usr/share/icingaweb2/modules/x509/schema/mysql-upgrades/1.3.0.sql
```

### Migrate Jobs

Afterwards, you can safely migrate your jobs with the following command. Keep in mind that you need to specify an
Icinga Web username that will be used as the author of these jobs in the database.

```
# icingacli x509 migrate jobs --author "icingaadmin"
```

## Upgrading to version 1.2.0

Icinga Certificate Monitoring version 1.2.0 requires a schema update for the database. We have changed all `timestamp`
columns in the database to biguint to store all timestamps in milliseconds. The sort column `expires` has been dropped
as well, but you can sort the certificates by `valid_to` instead.

You may use the following command to apply the database schema upgrade file:
<!-- {% if not icingaDocs %} -->

**Note:** If you haven't installed this module from packages, then please adapt the schema path to the correct installation path.

<!-- {% endif %} -->
```sql
# mysql -u root -p x509 < /usr/share/icingaweb2/modules/x509/schema/mysql-upgrades/1.2.0.sql
```

## Upgrading to version 1.1.0

Icinga Certificate Monitoring version 1.1.0 fixes issues that affect the database schema.
To have these issues really fixed in your environment, the schema must be upgraded.
Please find the upgrade script in **/usr/share/icingaweb2/modules/x509/schema/mysql-upgrades**.

You may use the following command to apply the database schema upgrade file:
<!-- {% if not icingaDocs %} -->

**Note:** If you haven't installed this module from packages, then please adapt the schema path to the correct installation path.

<!-- {% endif %} -->

```
# mysql -u root -p x509 < /usr/share/icingaweb2/modules/x509/schema/mysql-upgrades/1.1.0.sql
```

## Upgrading to version 1.0.0

Icinga Certificate Monitoring version 1.0.0 requires a schema update for the database.
The schema has been adjusted so that it is no longer necessary to adjust server settings
if you're using a version of MySQL < 5.7 or MariaDB < 10.2.
Please find the upgrade script in **/user/share/icingaweb2/modules/x509/schema/mysql-upgrades**.

You may use the following command to apply the database schema upgrade file:
<!-- {% if not icingaDocs %} -->

**Note:** If you haven't installed this module from packages, then please adapt the schema path to the correct installation path.

<!-- {% endif %} -->

```
# mysql -u root -p x509 < /usr/share/icingaweb2/modules/x509/schema/mysql-upgrades/1.0.0.sql
```
