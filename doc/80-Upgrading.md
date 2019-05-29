# Upgrading Icinga Certificate Monitoring <a id="upgrading"></a>

Upgrading Icinga Certificate Monitoring is straightforward.
Usually the only manual steps involved are schema updates for the database.

## Upgrading to Version 1.0.0 <a id="upgrading-to-v1.0.0"></a>

Icinga Certificate Monitoring version 1.0.0 requires a schema update for the database.
The schema has been adjusted so that it is no longer necessary to adjust server settings
if you're using a version of MySQL < 5.7 or MariaDB < 10.2.
Please find the upgrade script in **etc/schema/mysql-upgrade**.

You may use the following command to apply the database schema upgrade file:

```
# mysql -u root -p x509 < etc/schema/mysql-upgrade/v1.0.0.sql
```
