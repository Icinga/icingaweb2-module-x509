# Installing Icinga Certificate Monitoring from Source

Please see the Icinga Web documentation on
[how to install modules](https://icinga.com/docs/icinga-web-2/latest/doc/08-Modules/#installation) from source.
Make sure you use `x509` as the module name. The following requirements must also be met.

## Requirements

* PHP (≥7.2)
* MySQL or PostgreSQL PDO PHP libraries
* The following PHP modules must be installed: `gmp`, `pcntl`, `openssl`
* [Icinga Web](https://github.com/Icinga/icingaweb2) (≥2.9)
* [Icinga PHP Library (ipl)](https://github.com/Icinga/icinga-php-library) (≥0.13.0)
* [Icinga PHP Thirdparty](https://github.com/Icinga/icinga-php-thirdparty) (≥0.12.0)

<!-- {% include "02-Installation.md" %} -->
