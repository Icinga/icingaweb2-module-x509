<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Data\ConfigObject;
use Icinga\Module\X509\React\StreamOptsCaptureConnector;
use Icinga\Util\StringHelper;
use ipl\Sql\Connection;
use ipl\Sql\Expression;
use ipl\Sql\Insert;
use ipl\Sql\Select;
use ipl\Sql\Update;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use React\Socket\SecureConnector;
use React\Socket\TimeoutConnector;

class Job
{
    /**
     * @var Connection
     */
    private $db;
    private $dbTool;
    private $loop;
    private $pendingTargets = 0;
    private $totalTargets = 0;
    private $finishedTargets = 0;
    private $targets;
    private $jobId;
    private $jobDescription;
    private $snimap;
    private $parallel;
    private $name;

    public function __construct($name, Connection $db, ConfigObject $jobDescription, array $snimap, $parallel)
    {
        $this->db = $db;
        $this->dbTool = new DbTool($db);
        $this->jobDescription = $jobDescription;
        $this->snimap = $snimap;
        $this->parallel = $parallel;
        $this->name = $name;
    }

    private function getConnector($peerName)
    {
        $simpleConnector = new Connector($this->loop);
        $streamCaptureConnector = new StreamOptsCaptureConnector($simpleConnector);
        $secureConnector = new SecureConnector($streamCaptureConnector, $this->loop, array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'capture_peer_cert_chain' => true,
            'SNI_enabled' => true,
            'peer_name' => $peerName
        ));
        return [new TimeoutConnector($secureConnector, 5.0, $this->loop), $streamCaptureConnector];
    }

    public static function binary($addr)
    {
        return str_pad(inet_pton($addr), 16, "\0", STR_PAD_LEFT);
    }

    private static function addrToNumber($addr)
    {
        return gmp_import(static::binary($addr));
    }

    private static function numberToAddr($num, $ipv6 = true)
    {
        if ((bool) $ipv6) {
            return inet_ntop(str_pad(gmp_export($num), 16, "\0", STR_PAD_LEFT));
        } else {
            return inet_ntop(gmp_export($num));
        }
    }

    private static function generateTargets(ConfigObject $jobDescription, array $hostnamesConfig)
    {
        foreach (StringHelper::trimSplit($jobDescription->get('cidrs')) as $cidr) {
            $pieces = explode('/', $cidr);
            if (count($pieces) !== 2) {
                Logger::warning("CIDR '%s' is in the wrong format.", $cidr);
                continue;
            }
            $start_ip = $pieces[0];
            $prefix = $pieces[1];
//            $subnet = 128;
//            if (substr($start_ip, 0, 2) === '::') {
//                if (strtoupper(substr($start_ip, 0, 7)) !== '::FFFF:') {
//                    $subnet = 32;
//                }
//            } elseif (strpos($start_ip, ':') === false) {
//                $subnet = 32;
//            }
            $ipv6 = filter_var($start_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
            $subnet = $ipv6 ? 128 : 32;
            $numIps = pow(2, ($subnet - $prefix)) - 2;

            Logger::info('Scanning %d IPs in the CIDR %s.', $numIps, $cidr);

            $start = static::addrToNumber($start_ip);
            for ($i = 0; $i < $numIps; $i++) {
                $ip = static::numberToAddr(gmp_add($start, $i), $ipv6);
                foreach (StringHelper::trimSplit($jobDescription->get('ports')) as $portRange) {
                    $pieces = StringHelper::trimSplit($portRange, '-');
                    if (count($pieces) === 2) {
                        list($start_port, $end_port) = $pieces;
                    } else {
                        $start_port = $pieces[0];
                        $end_port = $pieces[0];
                    }

                    foreach (range($start_port, $end_port) as $port) {
                        $hostnames = isset($hostnamesConfig[$ip]) ? $hostnamesConfig[$ip] : [];

                        if (empty($hostnames)) {
                            $hostnames[] = null;
                        }

                        foreach ($hostnames as $hostname) {
                            $target = (object)[];
                            $target->ip = $ip;
                            $target->port = $port;
                            $target->hostname = $hostname;
                            yield $target;
                        }
                    }
                }
            }
        }
    }

    private function updateJobStats($finished = false)
    {
        $fields = ['finished_targets' => $this->finishedTargets];

        if ($finished) {
            $fields['end_time'] = new Expression('NOW()');
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
        if (!$this->targets->valid()) {
            if ($this->pendingTargets == 0) {
                $this->updateJobStats(true);
                $this->loop->stop();
            }

            return;
        }

        $target = $this->targets->current();
        $this->targets->next();

        $url = "tls://[{$target->ip}]:{$target->port}";
        Logger::debug("Connecting to %s", static::formatTarget($target));
        $this->pendingTargets++;
        /** @var ConnectorInterface $connector */
        /** @var StreamOptsCaptureConnector $streamCapture */
        list($connector, $streamCapture) = $this->getConnector($target->hostname);
        $connector->connect($url)->then(
            function (ConnectionInterface $conn) use ($target, $streamCapture) {
                $this->finishTarget();

                Logger::info("Connected to %s", static::formatTarget($target));

                // Close connection in order to capture stream context options
                $conn->close();

                $capturedStreamOptions = $streamCapture->getCapturedStreamOptions();

                $this->processChain($target, $capturedStreamOptions['ssl']['peer_certificate_chain']);
            },
            function (\Exception $exception) use ($target, $streamCapture) {
                Logger::debug("Cannot connect to server: %s", $exception->getMessage());

                $this->finishTarget();

                $capturedStreamOptions = $streamCapture->getCapturedStreamOptions();

                if (isset($capturedStreamOptions['ssl']['peer_certificate_chain'])) {
                    // The scanned target presented its certificate chain despite throwing an error
                    // This is the case for targets which require client certificates for example
                    $this->processChain($target, $capturedStreamOptions['ssl']['peer_certificate_chain']);
                } else {
                    $this->db->update(
                        'x509_target',
                        ['latest_certificate_chain_id' => null],
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
                //$loop->stop();
            }
        )->otherwise(function (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            echo $e->getTraceAsString() . PHP_EOL;
        });
    }

    public function getJobId()
    {
        return $this->jobId;
    }

    public function run()
    {
        $this->loop = Factory::create();

        $this->totalTargets = iterator_count(static::generateTargets($this->jobDescription, $this->snimap));

        if ($this->totalTargets == 0) {
            return null;
        }

        $this->targets = static::generateTargets($this->jobDescription, $this->snimap);

        $this->db->insert(
            'x509_job_run',
            [
                'name'             => $this->name,
                'total_targets'    => $this->totalTargets,
                'finished_targets' => 0
            ]
        );

        $this->jobId = $this->db->lastInsertId();

        // Start scanning the first couple of targets...
        for ($i = 0; $i < $this->parallel; $i++) {
            $this->startNextTarget();
        }

        $this->loop->run();

        return $this->totalTargets;
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
            $row = $this->db->select(
                (new Select())
                    ->columns(['id'])
                    ->from('x509_target')
                    ->where([
                        'ip = ?'        => $this->dbTool->marshalBinary(static::binary($target->ip)),
                        'port = ?'      => $target->port,
                        'hostname = ?'  => $target->hostname
                    ])
            )->fetch();

            if ($row === false) {
                $this->db->insert(
                    'x509_target',
                    [
                        'ip'       => $this->dbTool->marshalBinary(static::binary($target->ip)),
                        'port'     => $target->port,
                        'hostname' => $target->hostname
                    ]
                );
                $targetId = $this->db->lastInsertId();
            } else {
                $targetId = $row['id'];
            }

            $chainUptodate = false;

            $lastChain = $this->db->select(
                (new Select())
                    ->columns(['id'])
                    ->from('x509_certificate_chain')
                    ->where(['target_id = ?' => $targetId])
                    ->orderBy('id', SORT_DESC)
                    ->limit(1)
            )->fetch();

            if ($lastChain !== false) {
                $lastFingerprints = $this->db->select(
                    (new Select())
                        ->columns(['c.fingerprint'])
                        ->from('x509_certificate_chain_link l')
                        ->join('x509_certificate c', 'l.certificate_id = c.id')
                        ->where(['l.certificate_chain_id = ?' => $lastChain[0]])
                        ->orderBy('l.order')
                )->fetchAll();

                foreach ($lastFingerprints as &$lastFingerprint) {
                    $lastFingerprint = $lastFingerprint[0];
                }

                $currentFingerprints = [];

                foreach ($chain as $cert) {
                    $currentFingerprints[] = openssl_x509_fingerprint($cert, 'sha256', true);
                }

                $chainUptodate = $currentFingerprints === $lastFingerprints;
            }

            if ($chainUptodate) {
                $chainId = $lastChain[0];
            } else {
                $this->db->insert(
                    'x509_certificate_chain',
                    [
                        'target_id' => $targetId,
                        'length'    => count($chain)
                    ]
                );

                $chainId = $this->db->lastInsertId();

                foreach ($chain as $index => $cert) {
                    $certInfo = openssl_x509_parse($cert);

                    $certId = CertificateUtils::findOrInsertCert($this->db, $cert, $certInfo);

                    $this->db->insert(
                        'x509_certificate_chain_link',
                        [
                            'certificate_chain_id' => $chainId,
                            $this->db->quoteIdentifier('order') => $index,
                            'certificate_id'       => $certId
                        ]
                    );
                }
            }

            $this->db->update(
                'x509_target',
                ['latest_certificate_chain_id' => $chainId],
                ['id = ?' => $targetId]
            );
        });
    }
}
