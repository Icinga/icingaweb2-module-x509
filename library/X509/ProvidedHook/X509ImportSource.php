<?php

// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\ProvidedHook;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\X509\Common\Database;

abstract class X509ImportSource extends ImportSourceHook
{
    use Database;
}
