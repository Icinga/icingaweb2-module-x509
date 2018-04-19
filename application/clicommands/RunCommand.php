<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Clicommands;

use DateTime;
use Exception;
use Icinga\Application\Config as IniConfig;
use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Cli\Command;
use Icinga\Data\ResourceFactory;
use Icinga\Module\X509\CertificateUtils;
use ipl\Sql\Config as DbConfig;
use ipl\Sql\Connection;
use ipl\Sql\Expression;
use ipl\Sql\Insert;
use ipl\Sql\Select;
use ipl\Sql\Update;
use React\EventLoop\Factory;
use React\Socket\Connector;
use React\Socket\TimeoutConnector;
use React\Socket\SecureConnector;
use React\Socket\ConnectionInterface;

class RunCommand extends Command
{
    /**
     * @var Connection
     */
    private $db;

    private $loop;
    private $connector;
    private $pendingTargets = 0;
    private $totalTargets = 0;
    private $finishedTargets = 0;
    private $targets;

    private $job;
    private $jobId;

    private static function addrToNumber($addr) {
        return gmp_import(inet_pton($addr));
    }

    private static function numberToAddr($num) {
        return inet_ntop(str_pad(gmp_export($num), 16, "\0", STR_PAD_LEFT));
    }

    private static function generateTargets($job)
    {
        foreach (Config::module('x509', 'ipranges') as $cidr => $ports) {
            // Ew.
            if ($ports['job'] != $job) {
                continue;
            }

            $cidr = explode('/', $cidr);
            $start_ip = $cidr[0];
            $prefix = $cidr[1];
            $ip_count = 1 << (128 - $prefix);
            $start = RunCommand::addrToNumber($start_ip);
            for ($i = 0; $i < $ip_count; $i++) {
                $ip = RunCommand::numberToAddr(gmp_add($start, $i));
                foreach ($ports as $start_port => $end_port) {
                    // Ew.
                    if ($start_port == 'job') {
                        continue;
                    }

                    foreach (range($start_port, $end_port) as $port) {
                        $target = (object) [];
                        $target->ip = $ip;
                        $target->port = $port;
                        yield $target;
                    }
                }
            }
        }
    }

    private function updateJobStats($finished = false) {
        $fields = ['finished_targets' => $this->finishedTargets];

        if ($finished) {
            $fields['end_time'] = new Expression('NOW()');
        }

        $this->db->update(
            (new Update())
                ->table('job_run')
                ->set($fields)
                ->where(['id = ?' => $this->jobId])
        );
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
        Logger::debug("Connecting to %s", $url);
        $this->pendingTargets++;
        $this->connector->connect($url)->then(
            function (ConnectionInterface $conn) use ($target) {
                $this->finishTarget();

                Logger::info("Connected to %s", $conn->getRemoteAddress());

                $stream = $conn->stream;
                $options = stream_context_get_options($stream);

                $conn->close();

                $chain = $options['ssl']['peer_certificate_chain'];

                $this->db->transaction(function () use($target, $chain) {
                    $row = $this->db->select(
                        (new Select())
                            ->columns(['id'])
                            ->from('target')
                            ->where(['ip = ?' => inet_pton($target->ip), 'port = ?' => $target->port, 'sni_name = ?' => '' ])
                    )->fetch();

                    if ($row === false) {
                        $this->db->insert(
                            (new Insert())
                                ->into('target')
                                ->columns(['ip', 'port', 'sni_name'])
                                ->values([inet_pton($target->ip), $target->port, ''])
                        );
                        $targetId = $this->db->lastInsertId();
                    } else {
                        $targetId = $row['id'];
                    }

                    $this->db->insert(
                        (new Insert())
                            ->into('certificate_chain')
                            ->columns(['target_id', 'length'])
                            ->values([$targetId, count($chain)])
                    );
                    $chainId = $this->db->lastInsertId();

                    $this->db->update(
                        (new Update())
                            ->table('target')
                            ->set(['latest_certificate_chain_id' => $chainId])
                            ->where(['id = ?' => $targetId])
                    );

                    foreach ($chain as $index => $cert) {
                        $certInfo = openssl_x509_parse($cert);

                        $certId = CertificateUtils::findOrInsertCert($this->db, $cert, $certInfo);

                        $this->db->insert(
                            (new Insert())
                                ->into('certificate_chain_link')
                                ->columns(['certificate_chain_id', '`order`', 'certificate_id'])
                                ->values([$chainId, $index, $certId])
                        );
                    }
                });
            },
            function (Exception $exception) use($target) {
                Logger::debug("Cannot connect to server: %s", $exception->getMessage());

                $this->db->update(
                    (new Update())
                        ->table('target')
                        ->set(['latest_certificate_chain_id' => null])
                        ->where(['ip = ?' => inet_pton($target->ip), 'port = ?' => $target->port, 'sni_name = ?' => '' ])
                );

                $this->finishTarget();

                $step = max($this->totalTargets / 100, 1);

                if ($this->finishedTargets % $step == 0) {
                    $this->updateJobStats();
                }
                //$loop->stop();
            }
        )->otherwise(function($ex) {
            var_dump($ex);
        });
    }

    function finishTarget()
    {
        $this->pendingTargets--;
        $this->finishedTargets++;
        $this->startNextTarget();
    }

    public function init()
    {
        $this->job = $this->params->shift('job');
    }

    /**
     * Scans IP and port ranges to find X.509 certificates.
     *
     * This command starts scanning the IP and port ranges which belong to the job that was
     * specified with the --job parameter.
     *
     * USAGE
     *
     * icingacli x509 scan --job <name>
     */
    public function indexAction()
    {
        if ($this->job == '') {
            Logger::warning("A job name must be specified with the --job option.");
            exit(1);
        }

        $config = new DbConfig(ResourceFactory::getResourceConfig(
            IniConfig::module('x509')->get('backend', 'resource')
        ));
        $this->db = new Connection($config);

        $this->loop = Factory::create();

        $simpleConnector = new Connector($this->loop);
        $secureConnector = new SecureConnector($simpleConnector, $this->loop, array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'capture_peer_cert_chain' => true,
        ));
        $this->connector = new TimeoutConnector($secureConnector, 5.0, $this->loop);

        $this->totalTargets = iterator_count(RunCommand::generateTargets($this->job));

        if ($this->totalTargets == 0) {
            Logger::warning("The job '%s' does not have any targets.", $this->job);
            exit(1);
        }

        $this->db->insert(
            (new Insert())
                ->into('job_run')
                ->values([
                    'name' => $this->job,
                    'total_targets' => $this->totalTargets
                ])
        );

        $this->jobId = $this->db->lastInsertId();

        $this->targets = RunCommand::generateTargets($this->job);

        // Start scanning the first couple of targets...
        for ($i = 0; $i < 256; $i++) {
            $this->startNextTarget();
        }

        $this->loop->run();

        Logger::info("Scanned %s target%s.", $this->finishedTargets, $this->finishedTargets != 1 ? 's' : '');

        $verified = CertificateUtils::verifyCertificates($this->db);
        Logger::info("Checked certificate chain for %s certificate%s.", $verified, $verified != 1 ? 's' : '');
    }
}
