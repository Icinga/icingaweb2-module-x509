<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509;

class IPAddressFormatter
{
    /**
     * Pretty-prints the CIDR
     *
     * @return string
     */
    public static function prettyPrintCIDR($cidr)
    {
        /** @var Form $this */

        list($prefix, $bits) = explode('/', $cidr);
        $v6addr = unpack('n*', inet_pton($prefix));
        if ($v6addr[1] != 0 || $v6addr[2] != 0 || $v6addr[3] != 0 || $v6addr[4] != 0 || $v6addr[5] != 0 || $v6addr[6] != 0xffff) {
            return $cidr;
        }

        $v4addr = pack('n*', $v6addr[7], $v6addr[8]);
        $prefix = inet_ntop($v4addr);
        $bits -= 96;
        return "${prefix}/${bits}";
    }
}