# <a id="Installation"></a>Installation

## Requirements

* Icinga Web 2 (&gt;= 2.5)
* PHP (&gt;= 5.6, preferably 7.x)
* php-gmp
* OpenSSL
* MySQL / MariaDB or PostgreSQL
* Icinga Web 2 modules:
  * [reactbundle](https://github.com/Icinga/icingaweb2-module-reactbundle) (>= 0.4) (Icinga Web 2 module)
  * [Icinga PHP Library (ipl)](https://github.com/Icinga/icingaweb2-module-ipl) (>= 0.1) (Icinga Web 2 module)

## Database Setup

### MySQL / MariaDB

The module needs a MySQL/MariaDB database with the schema that's provided in the `etc/schema/mysql.schema.sql` file.

You can use the following sample command for creating the MySQL/MariaDB database. Please change the password:

```
CREATE DATABASE x509;
GRANT SELECT, INSERT, UPDATE, DELETE, DROP, CREATE VIEW, INDEX, EXECUTE ON x509.* TO x509@localhost IDENTIFIED BY 'secret';
```

After, you can import the schema using the following command:

```
mysql -p -u root x509 < etc/schema/mysql.schema.sql
```

## PostgreSQL

The module needs a PostgreSQL database with the schema that's provided in the `etc/schema/postgresql.schema.sql` file.

You can use the following sample command for creating the PostgreSQL database. Please change the password:

```sql
CREATE USER x509 WITH PASSWORD 'secret';
CREATE DATABASE x509
  WITH OWNER x509
  ENCODING 'UTF8'
  LC_COLLATE = 'en_US.UTF-8'
  LC_CTYPE = 'en_US.UTF-8';
```

After, you can import the schema using the following command:

```
psql -U x509 x509 -a -f etc/schema/postgresql.schema.sql
```

## Installation

1. Just drop this module to a `x509` subfolder in your Icinga Web 2 module path.

2. Log in with a privileged user in Icinga Web 2 and enable the module in `Configuration -> Modules -> x509`.
Or use the `icingacli` and run `icingacli module enable x509`.

3. Once you've set up the database, create a new Icinga Web 2 resource for it using the
`Configuration -> Application -> Resources` menu.

4. The next step involves telling the module which database resource to use. This can be done in
`Configuration -> Modules -> x509 -> Backend`.

This concludes the installation. You should now be able to import CA certificates and set up scan jobs.
Please read the [Configuration](03-Configuration.md) section for details.
