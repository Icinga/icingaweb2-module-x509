# <a id="Scanning"></a>Scanning

The Icinga Certificate Monitoring provides CLI commands to scan **hosts** and **IPs** in various ways.
These commands are listed below and can be used individually. It is necessary for all commands to know which IP address
ranges and ports to scan. These can be configured as described [here](03-Configuration.md#configure-jobs).

## Scan Command

The scan command, scans targets to find their X.509 certificates and track changes to them.
A **target** is an **IP-port** combination that is generated from the job configuration, taking into account configured
[**SNI**](03-Configuration.md#server-name-indication) maps, so that targets with multiple certificates are also properly
scanned.

By default, successive calls to the scan command perform partial scans, checking both targets not yet scanned and
targets whose scan is older than 24 hours, to ensure that all targets are rescanned over time and new certificates
are collected. This behavior can be customized through the command [options](#usage-1).

> **Note**
>
> When rescanning due targets, they will be rescanned regardless of whether the target previously provided a certificate
> or not, to collect new certificates, track changed certificates, and remove decommissioned certificates.

### Usage

This scan command can be used like any other Icinga Web cli operations like this: `icingacli x509 scan [OPTIONS]`

**Options:**

```
--job=<name>                Scan targets that belong to the specified job. (Required)
--since-last-scan=<time>    Scan targets whose last scan is older than the spcified date/time, which can also be an
                            English textual datetime description like "2 days". Defaults to "-24 hours".
--rescan                    Rescn only targets that have been scanned before.
--full                      (Re)scan all known and unknown targets. This will override the "rescan" and "since-last-scan" options.
--parallel=<number>         Allow parallel scanning of targets up to the specified number. Defaults to 256.
                            May cause **too many open files** error if set to a number higher than the configured one (ulimit).
```

#### Example

Scan all targets that have not yet been scanned, or whose last scan is older than a certain date/time:
```
# icingacli x509 scan --job <name> --since-last-scan '3 days'
```

Scan only **unknown** targets:
```
# icingacli x509 scan --job <name> --since-last-scan=null
```

Scan only known targets:
```
# icingacli x509 scan --job <name> --rescan
```

Scan only known targets whose last scan is older than certain a given date/time:
```
# icingacli x509 scan --job <name> --rescan --since-last-scan '5 days'
```

Scan all known and unknown targets:
```
# icingacli x509 scan --job <name> --full
```

## Scheduling Jobs

The jobs command is similar to the [scan command](#scan-command), but it additionally allows you to schedule your jobs
in a more convenient way. This is used by the default `systemd` service of this module as well. By default, this command
will run all your configured jobs based on their frequency. This behaviour can be customized through the command options
too. Since you can have multiple schedules for a single job, all job schedules can also be scheduled individually.

### Usage

This scan command can be used like any other Icinga Web cli operations like this: `icingacli x509 jobs run [OPTIONS`

**Options:**

```
--job=<name>            Run all configured schedules only of the specified job.
--schedule=<name>       Run only the given schedule of the specified job.
                        Providing a schedule name without a job will fail immediately.
--parallel=<number>     Allow parallel scanning of targets up to the specified number. Defaults to 256.
                        May cause **too many open files** error if set to a number higher than the configured one (ulimit).
```
