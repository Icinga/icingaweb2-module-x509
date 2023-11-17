# <a id="Datbase Housekeeping"></a>Database Housekeeping

Your database may grow over time and contain some outdated information. Icinga Certificate Monitoring provides you
the ability to clean up these outdated info in an easy way.

## Certificates and Targets

The default `cleanup` action removes targets whose last scan is older than a certain date/time and certificates that
are no longer used.

By default, any targets whose last scan is older than `1 month` are removed. The last scan information is always updated
when scanning a target, regardless of whether a successful connection is made or not. Therefore, targets that have been
decommissioned or are no longer part of a job configuration are removed after the specified period. Any certificates
that are no longer used are also removed. This can either be because the associated target has been removed or because
it is presenting a new certificate chain.

The `cleanup` command will also remove additionally all jobs activities created before the given date/time.
Jobs activities are usually just some stats about the job runs performed by the scheduler or/and manually
executed using the [scan](04-Scanning.md#scan-command) and/or [jobs](04-Scanning.md#scheduling-jobs) command.

### Usage

This command can be used like any other Icinga Web cli operations like this: `icingacli x509 cleanup [OPTIONS]`

**Options:**

```
--since-last-scan=<datetime>    Clean up targets whose last scan is older than the specified date/time,
                                which can also be an English textual datetime description like "2 days".
                                Defaults to "1 month".
```

#### Example

Remove any targets that have not been scanned for at least two months and any certificates that are no longer used.
```
icingacli x509 cleanup --since-last-scan="2 months"
```
