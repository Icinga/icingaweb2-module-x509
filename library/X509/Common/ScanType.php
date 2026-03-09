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

    public function label(): string
    {
        return match ($this) {
            self::FULL    => t('Full Scan'),
            self::PARTIAL => t('Partial Scan'),
            self::RE      => t('Rescan')
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FULL    => t('Scan all known and unknown targets of this job.'),
            self::PARTIAL => t('Scanning both, targets not yet scanned and targets'
                . ' whose scan is older than the specified time.'),
            self::RE      => t('Scan only targets that have been scanned before.')
        };
    }
}
