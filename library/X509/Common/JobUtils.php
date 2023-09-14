<?php

// Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Common;

use GMP;
use Icinga\Application\Logger;
use ipl\Stdlib\Str;

trait JobUtils
{
    /**
     * Parse the given comma separated CIDRs
     *
     * @param string $cidrs
     *
     * @return array<string, array<int, int|string>>
     */
    public function parseCIDRs(string $cidrs): array
    {
        $result = [];
        foreach (Str::trimSplit($cidrs) as $cidr) {
            $pieces = Str::trimSplit($cidr, '/');
            if (count($pieces) !== 2) {
                Logger::warning('CIDR %s is in the wrong format', $cidr);
                continue;
            }

            $result[$cidr] = $pieces;
        }

        return $result;
    }

    /**
     * Parse the given comma separated ports
     *
     * @param string $ports
     *
     * @return array<int, array<string>>
     */
    public function parsePorts(string $ports): array
    {
        $result = [];
        foreach (Str::trimSplit($ports) as $portRange) {
            $pieces = Str::trimSplit($portRange, '-');
            if (count($pieces) === 2) {
                list($start, $end) = $pieces;
            } else {
                $start = $pieces[0];
                $end = $pieces[0];
            }

            $result[] = [$start, $end];
        }

        return $result;
    }

    /**
     * Parse the given comma separated excluded targets
     *
     * @param ?string $excludes
     *
     * @return array<string>
     */
    public function parseExcludes(?string $excludes): array
    {
        $result = [];
        if (! empty($excludes)) {
            $result = array_flip(Str::trimSplit($excludes));
        }

        return $result;
    }
}
