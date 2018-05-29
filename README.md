# Icinga Web 2 X.509 Module

The X.509 module for Icinga Web 2 keeps track of certificates as they're deployed in a network environment. It does
this by scanning a range of IP addresses and ports for TLS services and collects whatever certificates it finds
along the way. The module's web frontend can be used to view scan results.

## Requirements

* Icinga Web 2
* MySQL
* OpenSSL
* php-gmp

### Database Setup

The module also needs a MySQL database with the schema that's provided in the `etc/schema/mysql.schema.sql` file.

Example command for creating the MySQL database. Please change the password:

```
CREATE DATABASE x509;
GRANT SELECT, INSERT, UPDATE, DELETE, DROP, CREATE VIEW, INDEX, EXECUTE ON x509.* TO 'x509'@'localhost' IDENTIFIED BY 'secret';
```

After, you can import the schema using the following command:

```
mysql -p -u x509 x509 < etc/schema/mysql.schema.sql
```

Note that if you're using a version of MySQL < 5.7, the following server options must be set:

```
innodb_file_format=barracuda
innodb_file_per_table=1
innodb_large_prefix=1
```

4. The next step involves telling the X.509 module which database resource to use. This can be done in
`Configuration -> Modules -> x509 -> Backend`.

## Installation

1. You can install the X.509 module by extracting the installation archive in the `modules` directory for your
Icinga Web 2.

2. Log in with a privileged user in Icinga Web 2 and enable the module in `Configuration -> Modules -> x509`.

3. Once you've set up the database, create a new Icinga Web 2 resource for it using the
`Configuration -> Application -> Resources` menu.

This concludes the installation. You should now be able to import CA certificates and set up scan jobs:

## Importing CA certificates

The X.509 module tries to verify certificates using its own trust store. By default this trust store is empty and it
is up to the Icinga Web 2 admin to import CA certificates into it.

Using the `icingacli x509 import` command CA certificates can be imported. The certificate chain file that is specified
with the `--file` option should contain a PEM-encoded list of X.509 certificates which should be added to the trust
store:

```
$ icingacli x509 import --file /etc/ssl/certs/ca-certificates.crt
Processed 148 X.509 certificates.
```

## Scan Jobs

The X.509 module needs to know which IP address ranges and ports to scan. These can be configured in
`Configuration -> Modules -> x509 -> Jobs`.

Scan jobs have a name which uniquely identifies them, e.g. `lan`. These names are used by the CLI command to start
scanning for specific jobs.

Each scan job can have one or more IP address ranges and one or more port ranges. The X.509 module scans each port in
a job's port ranges for all the individual IP addresses in the IP ranges.

IP address ranges have to be specified using the CIDR format. Multiple IP address ranges can be separated with commas,
e.g.:

`192.0.2.0/24,2001:db8::7e38/128`

Port ranges are separated with dashes (`-`). If you only want to scan a single port you don't need to specify the second
port:

`443,5665-5669`

Scan jobs can be executed using the `icingacli x509 scan` CLI command. The `--job` option is used to specify the scan
job which should be run:

```
$ icingacli x509 scan --job lan
Scanned 512 targets.
```
