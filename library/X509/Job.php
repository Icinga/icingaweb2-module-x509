<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use DateTime;
use Exception;
use Generator;
use Icinga\Application\Logger;
use Icinga\Data\ConfigObject;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Common\JobUtils;
use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Module\X509\Model\X509CertificateChain;
use Icinga\Module\X509\Model\X509Target;
use Icinga\Module\X509\React\StreamOptsCaptureConnector;
use Icinga\Util\Json;
use ipl\Scheduler\Contract\Task;
use ipl\Sql\Connection;
use ipl\Sql\Expression;
use ipl\Stdlib\Filter;
use ipl\Stdlib\Str;
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
    use JobUtils;

    /** @var Connection x509 database connection */
    private $db;

    /** @var DbTool Database utils for marshalling and unmarshalling binary data */
    private $dbTool;

    private $pendingTargets = 0;
    private $totalTargets = 0;
    private $finishedTargets = 0;

    /** @var Generator */
    private $targets;
    private $snimap;

    protected $jobId;

    /** @var Promise\Deferred React promise deferred instance used to resolve the running promise */
    protected $deferred;

    /** @var int Used to control how many targets can be scanned in parallel */
    protected $parallel;

    /** @var DateTime A formatted date time of this job start time */
    protected $jobRunStart;

    /** @var array A list of excluded IP addresses and host names */
    protected $excludedTargets = null;

    /** @var DateTime Since last scan threshold used to filter out scan targets */
    protected $sinceLastScan;

    /** @var bool Whether job run should only perform a rescan */
    protected $rescan = false;

    /** @var bool Whether the job run should perform a full scan */
    protected $fullScan = false;

    public function __construct(string $name, ConfigObject $config, array $snimap)
    {
        $this->db = $this->getDb();
        $this->dbTool = new DbTool($this->db);
        $this->snimap = $snimap;

        $this->setConfig($config);
        $this->setName($name);
        $this->setUuid(Uuid::fromBytes($this->getChecksum()));
    }

    /**
     * Get excluded IPs and host names
     *
     * @return array
     */
    protected function getExcludes(): array
    {
        if ($this->excludedTargets === null) {
            $config = $this->getConfig();
            $this->excludedTargets = [];
            if (isset($config['exclude_targets']) && ! empty($config['exclude_targets'])) {
                $this->excludedTargets = array_flip(Str::trimSplit($config['exclude_targets']));
            }
        }

        return $this->excludedTargets;
    }

    private function getConnector($peerName)
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
     * Get whether this task has been completed
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return ! $this->targets->valid() && $this->pendingTargets === 0;
    }

    public function getParallel(): int
    {
        return $this->parallel;
    }

    public function setParallel(int $parallel): self
    {
        $this->parallel = $parallel;

        return $this;
    }

    /**
     * Get whether this job run should do only a rescan
     *
     * @return bool
     */
    public function isRescan(): bool
    {
        return $this->rescan;
    }

    /**
     * Set whether this job run should do only a rescan or full scan
     *
     * @param bool $rescan
     *
     * @return $this
     */
    public function setRescan(bool $rescan): self
    {
        $this->rescan = $rescan;

        return $this;
    }

    /**
     * Set whether this job run should scan all known and unknown targets
     *
     * @param bool $fullScan
     *
     * @return $this
     */
    public function setFullScan(bool $fullScan): self
    {
        $this->fullScan = $fullScan;

        return $this;
    }

    /**
     * Set since last scan threshold for the targets to rescan
     *
     * @param ?DateTime $dateTime
     *
     * @return $this
     */
    public function setLastScan(?DateTime $dateTime): self
    {
        $this->sinceLastScan = $dateTime;

        return $this;
    }

    protected function updateLastScan($target)
    {
        if (! $this->isRescan()) {
            return;
        }

        $this->db->update('x509_target', [
            'last_scan' => new Expression('UNIX_TIMESTAMP()')
        ], ['id = ?' => $target->id]);
    }

    protected function getChecksum()
    {
        $config = $this->getConfig()->toArray();
        ksort($config);

        return md5($this->getName() . Json::encode($config), true);
    }

    protected function getScanTargets(): Generator
    {
        if (! $this->isRescan() || $this->fullScan) {
            yield from $this->generateTargets();
        }

        if ($this->sinceLastScan !== null || $this->isRescan()) {
            $targets = X509Target::on($this->db)->columns(['id', 'ip', 'hostname', 'port']);
            if (! $this->fullScan && $this->sinceLastScan) {
                $targets->filter(Filter::lessThan('last_scan', $this->sinceLastScan));
            }

            foreach ($targets as $target) {
                $addr = gmp_import($target->ip);
                $addrFound = false;
                foreach ($this->getCidrs() as $cidr) {
                    list($subnet, $mask) = $cidr;
                    if (static::isAddrInside($addr, $subnet, $mask)) {
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
        foreach ($this->getCidrs() as $cidr) {
            list($startIp, $prefix) = $cidr;
            $ipv6 = static::isIPV6($startIp);
            $subnet = $ipv6 ? 128 : 32;
            $numIps = pow(2, ($subnet - $prefix));

            Logger::info('Scanning %d IPs in the CIDR %s', $numIps, implode('/', $cidr));

            $start = static::addrToNumber($startIp);
            for ($i = 1; $i < $numIps - 1; $i++) {
                $ip = static::numberToAddr(gmp_add($start, $i), $ipv6);
                if (isset($excludes[$ip])) {
                    Logger::debug('Excluding IP %s from scan', $ip);
                    continue;
                }

                foreach ($this->getPorts() as $portRange) {
                    list($startPort, $endPort) = $portRange;
                    foreach (range($startPort, $endPort) as $port) {
                        foreach ($this->snimap[$ip] ?? [null] as $hostname) {
                            if (array_key_exists($hostname, $excludes)) {
                                Logger::debug('Excluding host %s from scan', $hostname);
                                continue;
                            }

                            $target = (object) [];
                            $target->ip = $ip;
                            $target->port = $port;
                            $target->hostname = $hostname;

                            if (! $this->fullScan) {
                                $targets = X509Target::on($this->db)
                                    ->columns([new Expression('1')])
                                    ->filter(
                                        Filter::all(
                                            Filter::equal('ip', $target->ip),
                                            Filter::equal('hostname', $target->hostname),
                                            Filter::equal('port', $target->port)
                                        )
                                    );

                                if ($targets->execute()->hasResult()) {
                                    continue;
                                }
                            }

                            yield $target;
                        }
                    }
                }
            }
        }
    }

    public function updateJobStats(bool $finished = false): void
    {
        $fields = [
            'finished_targets' => $this->finishedTargets,
            'mtime'            => new Expression('UNIX_TIMESTAMP() * 1000')
        ];

        if ($finished) {
            $fields['end_time'] = new Expression('UNIX_TIMESTAMP() * 1000');
            $fields['total_targets'] = $this->totalTargets;
        }

        $this->db->update(
            'x509_job_run',
            $fields,
            ['id = ?' => $this->jobId]
        );
    }

    private static function formatTarget($target)
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
        Logger::debug("Connecting to %s", static::formatTarget($target));

        /** @var ConnectorInterface $connector */
        /** @var StreamOptsCaptureConnector $streamCapture */
        list($connector, $streamCapture) = $this->getConnector($target->hostname);
        $connector->connect($url)->then(
            function (ConnectionInterface $conn) use ($target, $streamCapture) {
                Logger::info("Connected to %s", static::formatTarget($target));

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

            $this->db->insert('x509_job_run', [
                'name'             => $this->getName(),
                'start_time'       => $this->jobRunStart->getTimestamp() * 1000.0,
                'ctime'            => new Expression('UNIX_TIMESTAMP() * 1000'),
                'total_targets'    => 0,
                'finished_targets' => 0
            ]);

            $this->jobId = $this->db->lastInsertId();

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

        return $this->deferred->promise();
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
            $row = X509Target::on($this->db)->columns(['id']);

            $filter = Filter::all()
                ->add(Filter::equal('ip', $target->ip))
                ->add(Filter::equal('port', $target->port))
                ->add(Filter::equal('hostname', $target->hostname));

            $row->filter($filter);

            if (! ($row = $row->first())) {
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

            if ($chainUptodate) {
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
