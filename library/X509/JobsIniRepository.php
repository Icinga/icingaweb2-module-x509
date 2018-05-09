<?php
/* Icinga Web 2 X.509 Module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509;

use Icinga\Repository\IniRepository;

/**
 * Collection of jobs stored in the jobs.ini file
 */
class JobsIniRepository extends IniRepository
{
    protected $queryColumns = array('jobs' => array('name', 'cidrs', 'ports'));

    protected $configs = array('jobs' => array(
        'module'    => 'x509',
        'name'      => 'jobs',
        'keyColumn' => 'name'
    ));
}
