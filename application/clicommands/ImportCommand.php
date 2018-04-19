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
use Icinga\Module\X509\CertificateSignatureVerifier;
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

class PEMBlock
{
    public $type;
    public $bytes;

    function __construct($type, $data) {
        $this->type = $type;
        $this->data = $data;
    }
}

class ImportCommand extends Command
{
    /**
     * Reads PEM blocks from the specified file.
     *
     * @param $path The certificate file.
     * @return An array of PEM blocks.
     */
    private static function readPEMFile($path) {
        $lines = explode("\n", file_get_contents($path));
        $type = '';
        $data = '';
        foreach ($lines as $line) {
            if (strpos($line, '-----BEGIN ') === 0) {
                $type = substr($line, 11,strlen($line) - 11 - 5);
            }
            if ($type != '') {
                $data .= "${line}\n";
            }
            if (strpos($line, '-----END ') === 0) {
                yield new PEMBlock($type, $data);
                $type = '';
                $data = '';
            }
        }
    }

    public function init()
    {
        $this->file = $this->params->shift('file');
    }

    /**
     * Imports X.509 certificates..
     *
     * This command imports all X.509 certificates which are in the file specified by the --file parameter.
     *
     * USAGE
     *
     * icingacli x509 import --file <name>
     */
    public function indexAction()
    {
        if ($this->file == '') {
            Logger::warning("An X.509 certificate file must be specified with the --file parameter.");
            exit(1);
        }

        if (!file_exists($this->file)) {
            Logger::warning("The specified certificate file does not exist.");
            exit(1);
        }

        $config = new DbConfig(ResourceFactory::getResourceConfig(
            IniConfig::module('x509')->get('backend', 'resource')
        ));
        $db = new Connection($config);

        $blocks = ImportCommand::readPEMFile($this->file);

        $processed = 0;

        $db->transaction(function() use($db, $blocks, &$processed) {
            foreach ($blocks as $block) {
                if ($block->type != "CERTIFICATE") {
                    Logger::warning("Ignoring unknown PEM block type: %s", $block->type);
                }

                $cert = openssl_x509_read($block->data);
                $certId = CertificateUtils::findOrInsertCert($db, $cert, true);

                $db->update(
                    (new Update())
                        ->table('x509_certificate')
                        ->set([ 'trusted' => 'yes' ])
                        ->where([ 'id = ?' => $certId ])
                );

                $processed++;
            }
        });

        printf("Processed %d X.509 certificate%s.\n", $processed, $processed != 1 ? 's' : '');

        $verified = CertificateUtils::verifyCertificates($db);
        Logger::info("Checked certificate chain for %s certificate%s.", $verified, $verified != 1 ? 's' : '');
    }
}