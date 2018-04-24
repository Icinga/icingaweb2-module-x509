<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Config as IniConfig;
use Icinga\Application\Logger;
use Icinga\Cli\Command;
use Icinga\Data\ResourceFactory;
use Icinga\Module\X509\CertificateUtils;
use ipl\Sql\Config as DbConfig;
use ipl\Sql\Connection;
use ipl\Sql\Update;

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

    /**
     * Import all X.509 certificates from the given file and mark them as trusted
     *
     * USAGE:
     *
     *   icingacli x509 import --file <file>
     *
     * EXAMPLES:
     *
     *   icingacli x509 import --file /etc/ssl/certs/ca-bundle.crt
     */
    public function indexAction()
    {
        $file = $this->params->getRequired('file');

        if (! file_exists($file)) {
            Logger::warning('The specified certificate file does not exist.');
            exit(1);
        }

        $config = new DbConfig(ResourceFactory::getResourceConfig(
            IniConfig::module('x509')->get('backend', 'resource')
        ));
        $db = new Connection($config);

        $blocks = ImportCommand::readPEMFile($file);

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
