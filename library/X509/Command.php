<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\X509;

use Icinga\Application\Icinga;

class Command extends \Icinga\Cli\Command
{
    // Fix Web 2 issue where $configs is not properly initialized
    protected $configs = [];

    public function init()
    {
        Icinga::app()->getModuleManager()->loadEnabledModules();
    }
}
