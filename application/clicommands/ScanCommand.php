<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use Exception;
use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Common\JobUtils;
use Icinga\Module\X509\Hook\SniHook;
use Icinga\Module\X509\Job;
use Icinga\Module\X509\Model\X509Job;
use ipl\Stdlib\Filter;
use React\EventLoop\Loop;
use Throwable;

class ScanCommand extends Command
{
    use JobUtils;

    /**
     * Scan targets to find their X.509 certificates and track changes to them.
     *
     * A target is an IP-port combination that is generated from the job configuration, taking into account
     * configured SNI maps, so that targets with multiple certificates are also properly scanned.
     *
     * By default, successive calls to the scan command perform partial scans, checking both targets not yet scanned
     * and targets whose scan is older than 24 hours, to ensure that all targets are rescanned over time and new
     * certificates are collected. This behavior can be customized through the command options.
     *
     * Note that when rescanning due targets, they will be rescanned regardless of whether the target previously
     * provided a certificate or not, to collect new certificates, track changed certificates, and remove
     * decommissioned certificates.
     *
     * USAGE
     *
     *     icingacli x509 scan --job <name> [OPTIONS]
     *
     * OPTIONS
     *
     * --job=<name>
     *     Scan targets that belong to the specified job.
     *
     * --since-last-scan=<datetime>
     *     Scan targets whose last scan is older than the specified date/time,
     *     which can also be an English textual datetime description like "2 days".
     *     Defaults to "-24 hours".
     *
     * --parallel=<number>
     *     Allow parallel scanning of targets up to the specified number. Defaults to 256.
     *     May cause **too many open files** error if set to a number higher than the configured one (ulimit).
     *
     * --rescan
     *     Rescan only targets that have been scanned before.
     *
     * --full
     *     (Re)scan all known and unknown targets.
     *     This will override the "rescan" and "since-last-scan" options.
     *
     * EXAMPLES
     *
     * Scan all targets that have not yet been scanned, or whose last scan is older than a certain date/time:
     *
     *     icingacli x509 scan --job <name> --since-last-scan="3 days"
     *
     * Scan only unknown targets
     *
     *     icingacli x509 scan --job <name> --since-last-scan=null
     *
     * Scan only known targets
     *
     *     icingacli x509 scan --job <name> --rescan
     *
     * Scan only known targets whose last scan is older than a certain date/time:
     *
     *     icingacli x509 scan --job <name> --rescan --since-last-scan="5 days"
     *
     * Scan all known and unknown targets:
     *
     *     icingacli x509 scan --job <name> --full
     */
    public function indexAction(): void
    {
        /** @var string $name */
        $name = $this->params->shiftRequired('job');
        $fullScan = (bool) $this->params->get('full', false);
        $rescan = (bool) $this->params->get('rescan', false);

        /** @var string $sinceLastScan */
        $sinceLastScan = $this->params->get('since-last-scan', Job::DEFAULT_SINCE_LAST_SCAN);
        if ($sinceLastScan === 'null') {
            $sinceLastScan = null;
        }

        /** @var int $parallel */
        $parallel = $this->params->get('parallel', Job::DEFAULT_PARALLEL);
        if ($parallel <= 0) {
            throw new Exception('The \'parallel\' option must be set to at least 1');
        }

        /** @var X509Job $jobConfig */
        $jobConfig = X509Job::on(Database::get())
            ->filter(Filter::equal('name', $name))
            ->first();
        if ($jobConfig === null) {
            throw new Exception(sprintf('Job %s not found', $name));
        }

        if (! strlen($jobConfig->cidrs)) {
            throw new Exception(sprintf('The job %s does not specify any CIDRs', $name));
        }

        $cidrs = $this->parseCIDRs($jobConfig->cidrs);
        $ports = $this->parsePorts($jobConfig->ports);
        $job = (new Job($name, $cidrs, $ports, SniHook::getAll()))
            ->setId($jobConfig->id)
            ->setFullScan($fullScan)
            ->setRescan($rescan)
            ->setParallel($parallel)
            ->setExcludes($this->parseExcludes($jobConfig->exclude_targets))
            ->setLastScan($sinceLastScan);

        $promise = $job->run();
        $signalHandler = function () use (&$promise, $job) {
            $promise->cancel();

            Logger::info('Job %s canceled', $job->getName());

            Loop::futureTick(function () {
                Loop::stop();
            });
        };
        Loop::addSignal(SIGINT, $signalHandler);
        Loop::addSignal(SIGTERM, $signalHandler);

        $promise->then(function ($targets = 0) use ($job) {
            if ($targets === 0) {
                Logger::warning('The job %s does not have any targets', $job->getName());
            } else {
                Logger::info('Scanned %d target(s) from job %s', $targets, $job->getName());

                try {
                    $verified = CertificateUtils::verifyCertificates(Database::get());

                    Logger::info('Checked %d certificate chain(s)', $verified);
                } catch (Exception $err) {
                    Logger::error($err->getMessage());
                    Logger::debug($err->getTraceAsString());
                }
            }
        }, function (Throwable $err) use ($job) {
            Logger::error('Failed to run job %s: %s', $job->getName(), $err->getMessage());
            Logger::debug($err->getTraceAsString());
        })->finally(function () {
            Loop::futureTick(function () {
                Loop::stop();
            });
        });
    }
}
