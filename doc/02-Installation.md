<!-- {% if index %} -->
# Installing Icinga Certificate Monitoring

The recommended way to install Icinga Certificate Monitoring
and its dependencies is to use prebuilt packages for
all supported platforms from our official release repository.
Please note that [Icinga Web](https://icinga.com/docs/icinga-web) is required
and if it is not already set up, it is best to do this first.

The following steps will guide you through installing and setting up Icinga Certificate Monitoring.
<!-- {% else %} -->
<!-- {% if not icingaDocs %} -->

## Installing the Package

If the [repository](https://packages.icinga.com) is not configured yet, please add it first.
Then use your distribution's package manager to install the `icinga-x509` package
or install [from source](02-Installation.md.d/From-Source.md).
<!-- {% endif %} -->

## Setting up the Database

### Setting up a MySQL or MariaDB Database

The module needs a MySQL/MariaDB database with the schema that's provided in the `/usr/share/icingaweb2/modules/x509/schema/mysql.schema.sql` file.
<!-- {% if not icingaDocs %} -->

**Note:** If you haven't installed this module from packages, then please adapt the schema path to the correct installation path.

<!-- {% endif %} -->

You can use the following sample command for creating the MySQL/MariaDB database. Please change the password:

```
CREATE DATABASE x509;
GRANT CREATE, SELECT, INSERT, UPDATE, DELETE, DROP, ALTER, CREATE VIEW, INDEX, EXECUTE ON x509.* TO x509@localhost IDENTIFIED BY 'secret';
```

After, you can import the schema using the following command:

```
mysql -p -u root x509 < /usr/share/icingaweb2/modules/x509/schema/mysql.schema.sql
```

### Setting up a PostgreSQL Database

The module needs a PostgreSQL database with the schema that's provided in the `/usr/share/icingaweb2/modules/x509/schema/pgsql.schema.sql` file.
<!-- {% if not icingaDocs %} -->

**Note:** If you haven't installed this module from packages, then please adapt the schema path to the correct installation path.

<!-- {% endif %} -->

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
psql -U x509 x509 -a -f /usr/share/icingaweb2/modules/x509/schema/pgsql.schema.sql
```

This concludes the installation. You should now be able to import CA certificates and set up scan jobs.
Please read the [Configuration](03-Configuration.md) section for details.
<!-- {% endif %} --><!-- {# end else if index #} -->
