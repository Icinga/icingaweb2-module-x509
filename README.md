# Icinga Certificate Monitoring

[![PHP Support](https://img.shields.io/badge/php-%3E%3D%207.0-777BB4?logo=PHP)](https://php.net/)
![Build Status](https://github.com/icinga/icingaweb2-module-x509/workflows/PHP%20Tests/badge.svg?branch=master)
[![Github Tag](https://img.shields.io/github/tag/Icinga/icingaweb2-module-x509.svg)](https://github.com/Icinga/icingaweb2-module-x509)

![Icinga Logo](https://icinga.com/wp-content/uploads/2014/06/icinga_logo.png)

The certificate monitoring module for Icinga keeps track of certificates as they are deployed in a network environment.
It does this by scanning networks for TLS services and collects whatever certificates it finds along the way.
The certificates are verified using its own trust store.

The module’s web frontend can be used to view scan results, allowing you to drill down into detailed information
about any discovered certificate of your landscape:

![X.509 Usage](doc/res/x509-usage.png "X.509 Usage")

![X.509 Certificates](doc/res/x509-certificates.png "X.509 Certificates")

At a glance you see which CAs have issued your certificates and key counters of your environment:

![X.509 Dashboard](doc/res/x509-dashboard.png "X.509 Dashboard")

## Documentation

* [Installation](doc/02-Installation.md)
* [Configuration](doc/03-Configuration.md)
* [Monitoring](doc/10-Monitoring.md)
