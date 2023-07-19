<?php

// Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Common;

use GMP;
use Icinga\Application\Logger;
use ipl\Stdlib\Str;

trait JobUtils
{
    /** @var array<string> A list of excluded IP addresses and host names */
    private $excludedTargets = [];

    /** @var array<string, array<int, int|string>> */
    private $cidrs = [];

    /** @var array<int, array<string>> */
    private $ports = [];

    /**
     * Get the configured job CIDRS
     *
     * @return array<string, array<int, int|string>>
     */
    public function getCIDRs(): array
    {
        return $this->cidrs;
    }

    /**
     * Set the CIDRs of this job
     *
     * @param string $cidrs
     *
     * @return $this
     */
    public function setCIDRs(string $cidrs): self
    {
        foreach (Str::trimSplit($cidrs) as $cidr) {
            $pieces = Str::trimSplit($cidr, '/');
            if (count($pieces) !== 2) {
                Logger::warning('CIDR %s is in the wrong format', $cidr);
                continue;
            }

            $this->cidrs[$cidr] = $pieces;
        }

        return $this;
    }

    /**
     * Get the configured ports of this job
     *
     * @return array<int, array<string>>
     */
    public function getPorts(): array
    {
        return $this->ports;
    }

    /**
     * Set the ports of this job to be scanned
     *
     * @param string $ports
     *
     * @return $this
     */
    public function setPorts(string $ports): self
    {
        foreach (Str::trimSplit($ports) as $portRange) {
            $pieces = Str::trimSplit($portRange, '-');
            if (count($pieces) === 2) {
                list($start, $end) = $pieces;
            } else {
                $start = $pieces[0];
                $end = $pieces[0];
            }

            $this->ports[] = [$start, $end];
        }

        return $this;
    }

    /**
     * Get excluded IPs and host names
     *
     * @return array<string>
     */
    public function getExcludes(): array
    {
        return $this->excludedTargets;
    }

    /**
     * Set a set of IPs and host names to be excluded from scan
     *
     * @param ?string $targets
     *
     * @return $this
     */
    public function setExcludes(?string $targets): self
    {
        if (! empty($targets)) {
            $this->excludedTargets = array_flip(Str::trimSplit($targets));
        }

        return $this;
    }

    /**
     * Transform the given human-readable IP address into a binary format
     *
     * @param string $addr
     *
     * @return string
     */
    public static function binary(string $addr): string
    {
        return str_pad(inet_pton($addr), 16, "\0", STR_PAD_LEFT);
    }

    /**
     * Transform the given human-readable IP address into GMP number
     *
     * @param string $addr
     *
     * @return ?GMP
     */
    public static function addrToNumber(string $addr): ?GMP
    {
        return gmp_import(static::binary($addr));
    }

    /**
     * Transform the given number into human-readable IP address
     *
     * @param $num
     * @param bool $ipv6
     *
     * @return false|string
     */
    public static function numberToAddr($num, bool $ipv6 = true)
    {
        if ($ipv6) {
            return inet_ntop(str_pad(gmp_export($num), 16, "\0", STR_PAD_LEFT));
        } else {
            return inet_ntop(gmp_export($num));
        }
    }

    /**
     * Check whether the given IP is inside the specified CIDR
     *
     * @param GMP $addr
     * @param string $subnet
     * @param int $mask
     *
     * @return bool
     */
    public static function isAddrInside(GMP $addr, string $subnet, int $mask): bool
    {
        // `gmp_pow()` is like PHP's pow() function, but handles also very large numbers
        // and `gmp_com()` is like the bitwise NOT (~) operator.
        $mask = gmp_com(gmp_pow(2, (static::isIPV6($subnet) ? 128 : 32) - $mask) - 1);
        return gmp_strval(gmp_and($addr, $mask)) === gmp_strval(gmp_and(static::addrToNumber($subnet), $mask));
    }

    /**
     * Get whether the given IP address is IPV6 address
     *
     * @param $addr
     *
     * @return bool
     */
    public static function isIPV6($addr): bool
    {
        return filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
}
