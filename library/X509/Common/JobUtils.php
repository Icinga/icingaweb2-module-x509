<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Common;

use GMP;
use Icinga\Application\Logger;
use Icinga\Data\ConfigObject;
use Icinga\Util\StringHelper;

trait JobUtils
{
    /** @var ConfigObject */
    private $jobConfig;

    private $cidrs = [];

    private $ports = [];

    public function setJobConfig(ConfigObject $jobConfig): self
    {
        $this->jobConfig = $jobConfig;

        return $this;
    }

    public function getCidrs(): array
    {
        if (empty($this->cidrs) && ! $this->jobConfig->isEmpty()) {
            $cidrs = StringHelper::trimSplit($this->jobConfig->get('cidrs'));
            foreach ($cidrs as $cidr) {
                $pieces = StringHelper::trimSplit($cidr, '/');
                if (count($pieces) !== 2) {
                    Logger::warning("CIDR '%s' is in the wrong format.", $cidr);
                    continue;
                }

                $this->cidrs[$cidr] = $pieces;
            }
        }

        return $this->cidrs;
    }

    public function getPorts(): array
    {
        if (empty($this->ports) && ! $this->jobConfig->isEmpty()) {
            $ports = StringHelper::trimSplit($this->jobConfig->get('ports'));
            foreach ($ports as $portRange) {
                $pieces = StringHelper::trimSplit($portRange, '-');
                if (count($pieces) === 2) {
                    list($start, $end) = $pieces;
                } else {
                    $start = $pieces[0];
                    $end = $pieces[0];
                }

                $this->ports[] = [$start, $end];
            }
        }

        return $this->ports;
    }

    public static function binary(string $addr)
    {
        return str_pad(inet_pton($addr), 16, "\0", STR_PAD_LEFT);
    }

    public static function addrToNumber(string $addr)
    {
        return gmp_import(self::binary($addr));
    }

    public static function numberToAddr($num, bool $ipv6 = true)
    {
        if ($ipv6) {
            return inet_ntop(str_pad(gmp_export($num), 16, "\0", STR_PAD_LEFT));
        } else {
            return inet_ntop(gmp_export($num));
        }
    }

    public static function isAddrInside(array $cidr, GMP $addr)
    {
        list($subnet, $mask) = $cidr;
        $mask = pow(2, ((self::isIPV6($addr) ? 128 : 32) - $mask)) - 1;
        $subnet = self::addrToNumber($subnet);

        return (gmp_intval($addr) & ~$mask) === (gmp_intval($subnet) & ~$mask);
    }

    public static function isIPV6($addr)
    {
        return filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
}
