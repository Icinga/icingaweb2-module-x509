<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use DateTime;
use Exception;
use Generator;
use Icinga\Application\Logger;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Common\JobOptions;
use Icinga\Module\X509\Common\JobUtils;
use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Module\X509\Model\X509CertificateChain;
use Icinga\Module\X509\Model\X509JobRun;
use Icinga\Module\X509\Model\X509Target;
use Icinga\Module\X509\React\StreamOptsCaptureConnector;
use Icinga\Util\Json;
use ipl\Scheduler\Common\TaskProperties;
use ipl\Scheduler\Contract\Task;
use ipl\Sql\Connection;
use ipl\Sql\Expression;
use ipl\Stdlib\Filter;
use LogicException;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Loop;
use React\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use React\Socket\SecureConnector;
use React\Socket\TimeoutConnector;
use Throwable;

class Job implements Task
{
    use Database;
    use JobOptions;
    use JobUtils;
    use TaskProperties;

    /** @var int Number of targets to be scanned in parallel by default */
    public const DEFAULT_PARALLEL = 256;

    public const DEFAULT_SINCE_LAST_SCAN = '-24 hours';

    /** @var int The database id of this job */
    protected $id;

    /** @var Connection x509 database connection */
    private $db;

    /** @var DbTool Database utils for marshalling and unmarshalling binary data */
    private $dbTool;

    /** @var int Number of pending targets to be scanned */
    private $pendingTargets = 0;

    /** @var int Total number of scan targets */
    private $totalTargets = 0;

    /** @var int Number of scanned targets */
    private $finishedTargets = 0;

    /** @var Generator Scan targets generator */
    private $targets;

    /** @var array<string, array<string>> The configured SNI maps */
    private $snimap;

    /** @var int The id of the last inserted job run entry */
    private $jobRunId;

    /** @var Promise\Deferred React promise deferred instance used to resolve the running promise */
    protected $deferred;

    /** @var DateTime The start time of this job */
    protected $jobRunStart;

    public function __construct(string $name, string $cidrs, string $ports, array $snimap, Schedule $schedule = null)
    {
        $this->db = $this->getDb();
        $this->dbTool = new DbTool($this->db);
        $this->snimap = $snimap;

        if ($schedule) {
            $this->setSchedule($schedule);
        }

        $this->setName($name);
        $this->setCIDRs($cidrs);
        $this->setPorts($ports);
        $this->setUuid(Uuid::fromBytes($this->getChecksum()));
    }

    /**
     * Get the database id of this job
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the database id of this job
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    private function getConnector($peerName): array
    {
        $simpleConnector = new Connector();
        $streamCaptureConnector = new StreamOptsCaptureConnector($simpleConnector);
        $secureConnector = new SecureConnector($streamCaptureConnector, null, [
            'verify_peer'             => false,
            'verify_peer_name'        => false,
            'capture_peer_cert_chain' => true,
            'SNI_enabled'             => true,
            'peer_name'               => $peerName
        ]);
        return [new TimeoutConnector($secureConnector, 5.0), $streamCaptureConnector];
    }

    /**
     * Get whether this job has been completed scanning all targets
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return ! $this->targets->valid() && $this->pendingTargets === 0;
    }

    public function updateLastScan($target)
    {
        if (! $this->isRescan()) {
            return;
        }

        $this->db->update('x509_target', [
            'last_scan' => new Expression('UNIX_TIMESTAMP() * 1000')
        ], ['id = ?' => $target->id]);
    }

    public function getChecksum(): string
    {
        $data = [
            'name'            => $this->getName(),
            'cidrs'           => $this->getCIDRs(),
            'ports'           => $this->getPorts(),
            'exclude_targets' => $this->getExcludes(),
        ];

        $schedule = null;
        if ($this->schedule) {
            $schedule = $this->getSchedule();
        }

        return md5(Json::encode($data) . ($schedule ? bin2hex($schedule->getChecksum()) : ''), true);
    }

    protected function getScanTargets(): Generator
    {
        if (! $this->isRescan() || $this->fullScan) {
            yield from $this->generateTargets();
        }

        if ((! $this->fullScan && $this->sinceLastScan !== null) || $this->isRescan()) {
            $targets = X509Target::on($this->db)->columns(['id', 'ip', 'hostname', 'port']);
            if (! $this->fullScan && $this->sinceLastScan) {
                $targets->filter(Filter::lessThan('last_scan', $this->sinceLastScan));
            }

            foreach ($targets as $target) {
                $addr = static::addrToNumber($target->ip);
                $addrFound = false;
                foreach ($this->getCIDRs() as $cidr) {
                    list($subnet, $mask) = $cidr;
                    if (static::isAddrInside($addr, (string) $subnet, (int) $mask)) {
                        $target->ip = static::numberToAddr($addr, static::isIPV6($subnet));
                        $addrFound = true;

                        break;
                    }
                }

                if ($addrFound) {
                    yield $target;
                }
            }
        }
    }

    private function generateTargets(): Generator
    {
        $excludes = $this->getExcludes();
        foreach ($this->getCIDRs() as $cidr) {
            list($startIp, $prefix) = $cidr;
            $ipv6 = static::isIPV6($startIp);
            $subnet = $ipv6 ? 128 : 32;
            $numIps = pow(2, ($subnet - (int) $prefix));

            Logger::info('Scanning %d IPs in the CIDR %s', $numIps, implode('/', $cidr));

            $start = static::addrToNumber((string) $startIp);
            for ($i = 0; $i < $numIps; $i++) {
                $ip = static::numberToAddr(gmp_add($start, $i), $ipv6);
                if (isset($excludes[$ip])) {
                    Logger::debug('Excluding IP %s from scan', $ip);
                    continue;
                }

                foreach ($this->getPorts() as $portRange) {
                    list($startPort, $endPort) = $portRange;
                    foreach (range($startPort, $endPort) as $port) {
                        foreach ($this->snimap[$ip] ?? [null] as $hostname) {
                            if (array_key_exists((string) $hostname, $excludes)) {
                                Logger::debug('Excluding host %s from scan', $hostname);
                                continue;
                            }

                            if (! $this->fullScan) {
                                $targets = X509Target::on($this->db)
                                    ->columns([new Expression('1')])
                                    ->filter(
                                        Filter::all(
                                            Filter::equal('ip', $ip),
                                            Filter::equal('port', $port),
                                            $hostname !== null
                                                ? Filter::equal('hostname', $hostname)
                                                : Filter::unlike('hostname', '*')
                                        )
                                    );

                                if ($targets->execute()->hasResult()) {
                                    continue;
                                }
                            }

                            yield (object) [
                                'ip'       => $ip,
                                'port'     => $port,
                                'hostname' => $hostname
                            ];
                        }
                    }
                }
            }
        }
    }

    public function updateJobStats(bool $finished = false): void
    {
        $fields = ['finished_targets' => $this->finishedTargets];
        if ($finished) {
            $fields['end_time'] = new Expression('UNIX_TIMESTAMP() * 1000');
            $fields['total_targets'] = $this->totalTargets;
        }

        $this->db->update('x509_job_run', $fields, ['id = ?' => $this->jobRunId]);
    }

    private static function formatTarget($target): string
    {
        $result = "tls://[{$target->ip}]:{$target->port}";

        if ($target->hostname !== null) {
            $result .= " [SNI hostname: {$target->hostname}]";
        }

        return $result;
    }

    private function finishTarget()
    {
        $this->pendingTargets--;
        $this->finishedTargets++;
        $this->startNextTarget();
    }

    private function startNextTarget()
    {
        if ($this->isFinished()) {
            // No targets to process anymore, so we can now resolve the promise
            $this->deferred->resolve($this->finishedTargets);

            return;
        }

        if (! $this->targets->valid()) {
            // When nothing is yielded, and it's still not finished yet, just get the next target
            return;
        }

        $target = $this->targets->current();
        $this->targets->next();

        $this->totalTargets++;
        $this->pendingTargets++;

        $url = "tls://[{$target->ip}]:{$target->port}";
        Logger::debug("Connecting to %s", self::formatTarget($target));

        /** @var ConnectorInterface $connector */
        /** @var StreamOptsCaptureConnector $streamCapture */
        list($connector, $streamCapture) = $this->getConnector($target->hostname);
        $connector->connect($url)->then(
            function (ConnectionInterface $conn) use ($target, $streamCapture) {
                Logger::info("Connected to %s", self::formatTarget($target));

                // Close connection in order to capture stream context options
                $conn->close();

                $capturedStreamOptions = $streamCapture->getCapturedStreamOptions();

                $this->processChain($target, $capturedStreamOptions['ssl']['peer_certificate_chain']);

                $this->finishTarget();
            },
            function (Exception $exception) use ($target, $streamCapture) {
                Logger::debug("Cannot connect to server: %s", $exception->getMessage());

                $capturedStreamOptions = $streamCapture->getCapturedStreamOptions();

                if (isset($capturedStreamOptions['ssl']['peer_certificate_chain'])) {
                    // The scanned target presented its certificate chain despite throwing an error
                    // This is the case for targets which require client certificates for example
                    $this->processChain($target, $capturedStreamOptions['ssl']['peer_certificate_chain']);
                } else {
                    $this->db->update(
                        'x509_target',
                        [
                            'latest_certificate_chain_id' => null,
                            'mtime'                       => new Expression('UNIX_TIMESTAMP() * 1000')
                        ],
                        [
                            'hostname = ?' => $target->hostname,
                            'ip = ?'       => $this->dbTool->marshalBinary(static::binary($target->ip)),
                            'port = ?'     => $target->port
                        ]
                    );
                }

                $step = max($this->totalTargets / 100, 1);

                if ($this->finishedTargets % (int) $step == 0) {
                    $this->updateJobStats();
                }

                $this->finishTarget();
            }
        )->always(function () use ($target) {
            $this->updateLastScan($target);
        })->otherwise(function (Throwable $e) {
            Logger::error($e->getMessage());
            Logger::error($e->getTraceAsString());
        });
    }

    public function run(): Promise\ExtendedPromiseInterface
    {
        $this->jobRunStart = new DateTime();
        // Update the job statistics regardless of whether the job was successful, failed, or canceled.
        // Otherwise, some database columns might remain null.
        $updateJobStats = function () {
            $this->updateJobStats(true);
        };
        $this->deferred = new Promise\Deferred($updateJobStats);
        $this->deferred->promise()->always($updateJobStats);

        Loop::futureTick(function () {
            if (! $this->db->ping()) {
                $this->deferred->reject(new LogicException('Lost connection to database and failed to reconnect'));

                return;
            }

            // Reset those statistics for the next run! Is only necessary when
            // running this job using the scheduler
            $this->totalTargets = 0;
            $this->finishedTargets = 0;
            $this->pendingTargets = 0;

            if ($this->schedule) {
                $scheduleId = $this->getSchedule()->getId();
            } else {
                $scheduleId = new Expression('NULL');
            }

            $this->db->insert('x509_job_run', [
                'job_id'           => $this->getId(),
                'schedule_id'      => $scheduleId,
                'start_time'       => $this->jobRunStart->getTimestamp() * 1000.0,
                'total_targets'    => 0,
                'finished_targets' => 0
            ]);
            $this->jobRunId = (int) $this->db->lastInsertId();

            $this->targets = $this->getScanTargets();

            if ($this->isFinished()) {
                // There are no targets to scan, so we can resolve the promise earlier
                $this->deferred->resolve(0);

                return;
            }

            // Start scanning the first couple of targets...
            for ($i = 0; $i < $this->getParallel() && ! $this->isFinished(); $i++) {
                $this->startNextTarget();
            }
        });

        /** @var Promise\ExtendedPromiseInterface $promise */
        $promise = $this->deferred->promise();
        return $promise;
    }

    protected function processChain($target, $chain)
    {
        if ($target->hostname === null) {
            $hostname = gethostbyaddr($target->ip);

            if ($hostname !== false) {
                $target->hostname = $hostname;
            }
        }

        $this->db->transaction(function () use ($target, $chain) {
            $row = X509Target::on($this->db)
                ->columns(['id'])
                ->filter(
                    Filter::all(
                        Filter::equal('ip', $target->ip),
                        Filter::equal('port', $target->port),
                        Filter::equal('hostname', $target->hostname)
                    )
                )->first();

            if (! $row) {
                // TODO: https://github.com/Icinga/ipl-orm/pull/78
                $this->db->insert(
                    'x509_target',
                    [
                        'ip'        => $this->dbTool->marshalBinary(static::binary($target->ip)),
                        'port'      => $target->port,
                        'hostname'  => $target->hostname,
                        'last_scan' => new Expression('UNIX_TIMESTAMP() * 1000'),
                        'ctime'     => new Expression('UNIX_TIMESTAMP() * 1000')
                    ]
                );
                $targetId = $this->db->lastInsertId();
            } else {
                $targetId = $row->id;
            }

            $chainUptodate = false;

            $lastChain = X509CertificateChain::on($this->db)
                ->columns(['id'])
                ->filter(Filter::equal('target_id', $targetId))
                ->orderBy('id', SORT_DESC)
                ->limit(1)
                ->first();

            if ($lastChain) {
                $lastFingerprints = X509Certificate::on($this->db)->utilize('chain');
                $lastFingerprints
                    ->columns(['fingerprint'])
                    ->getSelectBase()
                    ->where(new Expression(
                        'certificate_link.certificate_chain_id = %d',
                        [$lastChain->id]
                    ))
                    ->orderBy('certificate_link.order');

                $lastFingerprintsArr = [];
                foreach ($lastFingerprints as $lastFingerprint) {
                    $lastFingerprintsArr[] = $lastFingerprint->fingerprint;
                }

                $currentFingerprints = [];

                foreach ($chain as $cert) {
                    $currentFingerprints[] = openssl_x509_fingerprint($cert, 'sha256', true);
                }

                $chainUptodate = $currentFingerprints === $lastFingerprintsArr;
            }

            if ($lastChain && $chainUptodate) {
                $chainId = $lastChain->id;
            } else {
                // TODO: https://github.com/Icinga/ipl-orm/pull/78
                $this->db->insert(
                    'x509_certificate_chain',
                    [
                        'target_id' => $targetId,
                        'length'    => count($chain),
                        'ctime'     => new Expression('UNIX_TIMESTAMP() * 1000')
                    ]
                );

                $chainId = $this->db->lastInsertId();

                $lastCertInfo = [];
                foreach ($chain as $index => $cert) {
                    $lastCertInfo = CertificateUtils::findOrInsertCert($this->db, $cert);
                    list($certId, $_) = $lastCertInfo;

                    $this->db->insert(
                        'x509_certificate_chain_link',
                        [
                            'certificate_chain_id'              => $chainId,
                            $this->db->quoteIdentifier('order') => $index,
                            'certificate_id'                    => $certId,
                            'ctime'                             => new Expression('UNIX_TIMESTAMP() * 1000')
                        ]
                    );

                    $lastCertInfo[] = $index;
                }

                // There might be chains that do not include the self-signed top-level Ca,
                // so we need to include it manually here, as we need to display the full
                // chain in the UI.
                $rootCa = X509Certificate::on($this->db)
                    ->columns(['id'])
                    ->filter(Filter::equal('subject_hash', $lastCertInfo[1]))
                    ->filter(Filter::equal('self_signed', true))
                    ->first();

                if ($rootCa && $rootCa->id !== $lastCertInfo[0]) {
                    $this->db->update(
                        'x509_certificate_chain',
                        ['length' => count($chain) + 1],
                        ['id = ?' => $chainId]
                    );

                    $this->db->insert(
                        'x509_certificate_chain_link',
                        [
                            'certificate_chain_id'              => $chainId,
                            $this->db->quoteIdentifier('order') => $lastCertInfo[2] + 1,
                            'certificate_id'                    => $rootCa->id,
                            'ctime'                             => new Expression('UNIX_TIMESTAMP() * 1000')
                        ]
                    );
                }
            }

            $this->db->update(
                'x509_target',
                [
                    'latest_certificate_chain_id' => $chainId,
                    'mtime'                       => new Expression('UNIX_TIMESTAMP() * 1000')
                ],
                ['id = ?' => $targetId]
            );
        });
    }
}
