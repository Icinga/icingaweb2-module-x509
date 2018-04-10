<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

use Icinga\Application\Icinga;
use ipl\Loader\CompatLoader;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/ipl/lib/ipl/Loader/CompatLoader.php';

CompatLoader::delegateLoadingToIcingaWeb(Icinga::app());