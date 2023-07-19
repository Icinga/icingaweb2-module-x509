# <a id="Configuration"></a>Configuration

## Importing CA certificates

The module tries to verify certificates using its own trust store. By default, this trust store is empty, and it
is up to the Icinga Web 2 admin to import CA certificates into it.

Using the `icingacli x509 import` command CA certificates can be imported. The certificate chain file that is specified
with the `--file` option should contain a PEM-encoded list of X.509 certificates which should be added to the trust
store:

```
icingacli x509 import --file /etc/ssl/certs/ca-certificates.crt
```

## Configure Jobs

Scan jobs have a name which uniquely identifies them, e.g. `lan`. These names are used by the CLI command to start
scanning for specific jobs.

Each scan job can have one or more IP address ranges and one or more port ranges. The module scans each port in
a job's port ranges for all the individual IP addresses in the IP ranges. IP address ranges have to be specified using
the CIDR format. Multiple IP address ranges can be separated with commas, e.g.:

`192.0.2.0/24,10.0.10.0/24`

Port ranges are separated with dashes (`-`). If you only want to scan a single port you don't need to specify the second
port:

`443,5665-5669`

Additionally, each job may also exclude specific **hosts** and **IP** addresses from scan. These hosts won't be scanned
when you run the [scan](04-Scanning.md#scan-command) or [jobs](04-Scanning.md#scheduling-jobs) command. Excluding an
entire network and specifying IP addresses in CIDR format will not work. You must specify a comma-separated concrete
**IP** and **host CN**, e.g:

`192.0.2.2,192.0.2.5,icinga.com`

### Job Schedules

Schedules are `cron` and [rrule](https://www.rfc-editor.org/rfc/rfc5545) based configs used to run periodically
at the given interval. Every job is allowed to have multiple schedules that can be run independently of each other.
Don't worry, you don't need to know anything about rrule to create **rrule** based schedules. All you need to do is
clicking some buttons over the UI. On the other hand, you should know what cron is and how to configure it to create
**cron**-based schedules. `Cron` based examples can be found [here](#cron-schedules).

Each job schedule provides different options that you can use to control the scheduling behavior of the
[jobs command](04-Scanning.md#scheduling-jobs).

#### Examples

##### RRule Schedules

A schedule that runs weekly on **Friday** and scans all targets that have not yet been scanned, or
whose last scan is older than `1 week`.

![Weekly Schedules](res/weekly-schedules.png "Weekly Schedules")


##### Cron Schedules

The `cron` format is as follows:

```
*    *    *    *    *
-    -    -    -    -
|    |    |    |    |
|    |    |    |    |
|    |    |    |    +----- day of week (0 - 6) (Sunday to Saturday)
|    |    |    +---------- month (1 - 12)
|    |    +--------------- day of month (1 - 31)
|    +-------------------- hour (0 - 23)
+------------------------- minute (0 - 59)
```

Example definitions:

| Description                                                | Definition |
|------------------------------------------------------------|------------|
| Run once a year at midnight of 1 January                   | 0 0 1 1 *  |
| Run once a month at midnight of the first day of the month | 0 0 1 * *  |
| Run once a week at midnight on Sunday morning              | 0 0 * * 0  |
| Run once a day at midnight                                 | 0 0 * * *  |
| Run once an hour at the beginning of the hour              | 0 * * * *  |

## Server Name Indication

In case you are serving multiple virtual hosts under a single IP you can configure those in
`Configuration -> Modules -> x509 -> SNI`.

Each entry defines an IP with multiple hostnames associated with it. These are then utilized when jobs run.

Modules may also provide sources for SNI. At this time the module monitoring is the only one with known support.
