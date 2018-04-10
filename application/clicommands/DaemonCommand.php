<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Clicommands;

use Icinga\Cli\Command;
use React\EventLoop\Factory;

class DaemonCommand extends Command
{
    public function indexAction()
    {
        $loop = Factory::create();

        $loop->run();
    }
}
