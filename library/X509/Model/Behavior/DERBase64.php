<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Model\Behavior;

use ipl\Orm\Contract\PropertyBehavior;

/**
 * Support automatically transformation of DER-encoded certificates to PEM and vice versa.
 */
class DERBase64 extends PropertyBehavior
{
    public function fromDb($value, $key, $_)
    {
        if (! $value) {
            return null;
        }

        $block = chunk_split(base64_encode($value), 64, "\n");

        return "-----BEGIN CERTIFICATE-----\n{$block}-----END CERTIFICATE-----";
    }

    public function toDb($value, $key, $_)
    {
        if (! $value) {
            return null;
        }

        $lines = explode("\n", $value);
        $der = '';

        foreach ($lines as $line) {
            if (strpos($line, '-----') === 0) {
                continue;
            }

            $der .= base64_decode($line);
        }

        return $der;
    }
}
