<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\X509\Common;

/**
 * This enum represents the available scan types
 */
enum ScanType: string
{
    case FULL = 'full';
    case PARTIAL = 'partial';
    case RE = 're';

    public static function fromConfig(string $fullScan, string $rescan): self
    {
        return match (true) {
            $fullScan === 'y' => self::FULL,
            $rescan === 'y'   => self::RE,
            default           => self::PARTIAL
        };
    }
}
