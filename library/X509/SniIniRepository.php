<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\X509;

use Icinga\Repository\IniRepository;

/**
 * Collection of hostnames stored in the sni.ini file
 */
class SniIniRepository extends IniRepository
{
    protected $queryColumns = array('sni' => array('ip', 'hostnames'));

    protected $configs = array('sni' => array(
        'module'    => 'x509',
        'name'      => 'sni',
        'keyColumn' => 'ip'
    ));
}
