# Upgrading Icinga Certificate Monitoring

Upgrading Icinga Certificate Monitoring is straightforward.
Usually the only manual steps involved are schema updates for the database.

## Upgrading to version 1.2.0

Icinga Certificate Monitoring version 1.2.0 requires a schema update for the database. We have changed all `timestamp`
columns in the database to biguint to store all timestamps in milliseconds. The sort column `expires` has been dropped
as well, but you can sort the certificates by `valid_to` instead.

You may use the following command to apply the database schema upgrade file:

```sql
# mysql -u root -p x509 < etc/schema/mysql-upgrades/1.2.0.sql
```

## Upgrading to version 1.1.0

Icinga Certificate Monitoring version 1.1.0 fixes issues that affect the database schema.
To have these issues really fixed in your environment, the schema must be upgraded.
Please find the upgrade script in **etc/schema/mysql-upgrade**.

You may use the following command to apply the database schema upgrade file:

```
# mysql -u root -p x509 < etc/schema/mysql-upgrade/1.1.0.sql
```

## Upgrading to version 1.0.0

Icinga Certificate Monitoring version 1.0.0 requires a schema update for the database.
The schema has been adjusted so that it is no longer necessary to adjust server settings
if you're using a version of MySQL < 5.7 or MariaDB < 10.2.
Please find the upgrade script in **etc/schema/mysql-upgrade**.

You may use the following command to apply the database schema upgrade file:

```
# mysql -u root -p x509 < etc/schema/mysql-upgrade/1.0.0.sql
```
