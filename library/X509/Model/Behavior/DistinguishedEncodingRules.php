<?php

namespace Icinga\Module\X509\Model\Behavior;

use ipl\Orm\Contract\PropertyBehavior;

class DistinguishedEncodingRules extends PropertyBehavior
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
