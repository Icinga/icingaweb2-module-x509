<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Logger;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\CertificateUtils;

class VerifyCommand extends Command
{
    /**
     * Verify all currently collected X.509 certificates
     *
     * USAGE:
     *
     *   icingacli x509 verify
     */
    public function indexAction()
    {
        $db = $this->getDb();

        $verified = CertificateUtils::verifyCertificates($db);

        Logger::info("Checked %d certificate chain%s.", $verified, $verified !== 1 ? 's' : '');
    }
}
