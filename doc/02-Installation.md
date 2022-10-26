# <a id="Installation"></a>Installation

## Requirements

* PHP (>= 7.0)
* Icinga Web 2 (>= 2.9)
* Icinga Web 2 libraries:
  * [Icinga PHP Library (ipl)](https://github.com/Icinga/icinga-php-library) (>= 0.8.1)
  * [Icinga PHP Thirdparty](https://github.com/Icinga/icinga-php-thirdparty) (>= 0.10)
* php-gmp
* php-pcntl (might already be built into your PHP binary)
* OpenSSL
* MySQL / MariaDB or PostgreSQL

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

1. Install it [like any other module](https://icinga.com/docs/icinga-web-2/latest/doc/08-Modules/#installation).
Use `x509` as name.

2. Once you've set up the database, create a new Icinga Web 2 resource for it using the
`Configuration -> Application -> Resources` menu.

3. The next step involves telling the module which database resource to use. This can be done in
`Configuration -> Modules -> x509 -> Backend`.

This concludes the installation. You should now be able to import CA certificates and set up scan jobs.
Please read the [Configuration](03-Configuration.md) section for details.
