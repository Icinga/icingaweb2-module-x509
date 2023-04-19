<?php

namespace Icinga\Module\X509\Model\Behavior;

use ipl\Orm\Behavior\Binary;
use ipl\Orm\Contract\PropertyBehavior;

/**
 * Support automatically transformation of human-readable IP addresses into their respective packed
 * binary representation and vice versa.
 */
class Ip extends Binary
{
    public function fromDb($value, $key, $_)
    {
        $value = parent::fromDb($value, $key, $_);
        if ($value === null) {
            return null;
        }

        $ipv4 = ltrim($value, "\0");
        if (strlen($ipv4) === 4) {
            $value = $ipv4;
        }

        return inet_ntop($value);
    }

    public function toDb($value, $key, $_)
    {
        if ($value === null || $value === '*' || ! ctype_print($value)) {
            return $value;
        }

        /** {@link https://github.com/Icinga/ipl-orm/issues/48} */
        if (substr($value, 0, 2) === '\\x') {
            return $value;
        }

        return parent::toDb(str_pad(inet_pton($value), 16, "\0", STR_PAD_LEFT), $key, $_);
    }
}
