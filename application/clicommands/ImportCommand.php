<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Logger;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\CertificateUtils;
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

        $db = $this->getDb();

        $bundle = CertificateUtils::parseBundle($file);

        $count = 0;

        $db->transaction(function (Connection $db) use ($bundle, &$count) {
            foreach ($bundle as $data) {
                $cert = openssl_x509_read($data);

                $id = CertificateUtils::findOrInsertCert($db, $cert);

                $db->update(
                    'x509_certificate',
                    ['trusted' => 'yes'],
                    ['id = ?' => $id]
                );

                $count++;
            }
        });

        printf("Processed %d X.509 certificate%s.\n", $count, $count !== 1 ? 's' : '');
    }
}
