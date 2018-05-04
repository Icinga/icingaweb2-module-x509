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

class ImportCommand extends Command
{
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

        $bundle = CertificateUtils::parseBundle($file);

        $count = 0;

        $db->transaction(function (Connection $db) use ($bundle, &$count) {
            foreach ($bundle as $data) {
                $cert = openssl_x509_read($data);
                $id = CertificateUtils::findOrInsertCert($db, $cert);

                $db->update(
                    (new Update())
                        ->table('x509_certificate')
                        ->set(['trusted' => 'yes'])
                        ->where(['id = ?' => $id])
                );

                $count++;
            }
        });

        printf("Processed %d X.509 certificate%s.\n", $count, $count !== 1 ? 's' : '');

        $verified = CertificateUtils::verifyCertificates($db);

        Logger::info("Checked certificate chain for %s certificate%s.", $verified, $verified !== 1 ? 's' : '');
    }
}
