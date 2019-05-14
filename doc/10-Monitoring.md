# <a id="Monitoring"></a>Monitoring

## Host Check Command

The module provides a CLI command to check a host's certificate. It does so by
fetching all the necessary information from this module's own database.

### Usage

General: `icingacli x509 check host [options]`

Options:

```
--ip                   A hosts IP address
--host                 A hosts name
--port                 The port to check in particular
--warning              Less remaining time results in state WARNING [25%]
--critical             Less remaining time results in state CRITICAL [10%]
--allow-self-signed    Ignore if a certificate or its issuer has been self-signed
```

### Threshold Definition

Thresholds can either be defined relative (in percent) or absolute (time interval).
Time intervals consist of a digit and an accompanying unit (e.g. "3M" are three
months). Supported units are:

 Identifier | Description
------------|------------
y, Y        | Year
M           | Month
d, D        | Day
h, H        | Hour
m           | Minute
s, S        | Second

**Example:**

```
$ icingacli x509 check host --host example.org --warning 1y
WARNING - *.example.org expires in 349 days|'*.example.org'=333;317;615;0;683
```

### Performance Data

The command outputs a performance data value for each certificate that is
served by the host. The value measured is the amount of days passed since
the certificate's first day of validity.

![check host perf data](res/check-host-perf-data.png)

The value of `max` is the total amount of days the certificate is valid.
`warning` and `critical` are the days after which the respective state is
reported.

## Icinga 2 Integration

Icinga 2 already provides an appropriate command in its template library:
https://icinga.com/docs/icinga2/latest/doc/10-icinga-template-library/#x509
