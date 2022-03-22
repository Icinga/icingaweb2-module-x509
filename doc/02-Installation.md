# <a id="Installation"></a>Installation

## Requirements

* PHP (>= 7.0)
* Icinga Web 2 (>= 2.9)
* Icinga Web 2 libraries:
  * [Icinga PHP Library (ipl)](https://github.com/Icinga/icinga-php-library) (>= 0.6)
  * [Icinga PHP Thirdparty](https://github.com/Icinga/icinga-php-thirdparty) (>= 0.10)
* php-gmp
* OpenSSL
* MySQL or MariaDB

## Database Setup

The module needs a MySQL/MariaDB database with the schema that's provided in the `etc/schema/mysql.schema.sql` file.

You may use the following example command for creating the MySQL/MariaDB database. Please change the password:

```
CREATE DATABASE x509;
GRANT SELECT, INSERT, UPDATE, DELETE, DROP, CREATE VIEW, INDEX, EXECUTE ON x509.* TO x509@localhost IDENTIFIED BY 'secret';
```

After, you can import the schema using the following command:

```
mysql -p -u root x509 < etc/schema/mysql.schema.sql
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
