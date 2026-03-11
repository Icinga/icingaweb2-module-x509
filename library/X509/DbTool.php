<?php

// SPDX-FileCopyrightText: 2020 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\X509;

use ipl\Sql\Connection;

class DbTool
{
    protected $pgsql = false;

    public function __construct(Connection $db)
    {
        $this->pgsql = $db->getConfig()->db === 'pgsql';
    }

    /**
     * @param string $binary
     *
     * @return string
     */
    public function marshalBinary($binary)
    {
        if ($this->pgsql) {
            return sprintf('\\x%s', bin2hex(static::unmarshalBinary($binary)));
        }

        return $binary;
    }

    /**
     * @param resource|string $binary
     *
     * @return string
     */
    public static function unmarshalBinary($binary)
    {
        if (is_resource($binary)) {
            return stream_get_contents($binary);
        }

        return $binary;
    }
}
