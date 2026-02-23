<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Logger;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Common\Database;

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
        $verified = CertificateUtils::verifyCertificates(Database::get());

        Logger::info("Checked %d certificate chain%s.", $verified, $verified !== 1 ? 's' : '');
    }
}
