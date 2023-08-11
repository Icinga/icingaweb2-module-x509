<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Tests\Icinga\Modules\X509\Model\Behavior;

use Icinga\Module\X509\Model\Behavior\DERBase64;
use PHPUnit\Framework\TestCase;

class DERBase64Test extends TestCase
{
    protected const COLUMN = 'cert';

    protected const CERT = <<<'EOD'
-----BEGIN CERTIFICATE-----
MIIBVgIBADANBgkqhkiG9w0BAQEFAASCAUAwggE8AgEAAkEA6QnU5eXu9ugwYsR3
LcHVZwpag+GlLzASRmQXoaWFTVVTsdnxYqFTs4+/raVtN0/GUtXX8YTN95VE1y/H
pwyTgQIDAQABAkEA3EtX/9BB+xR5kRSKWS4QTyzhbiRj49y8meBK2ps/DV8bP4nE
E6VadMSpWFIjuUKZ+D8rdI/7BNUPmgS7Gtk4BQIhAPd3u0fiFje2PWNye9mZX3f+
zbeAKXXrpWEGpNvi72wPAiEA8RK9fNLOBFUXsPtcGsQZD4DhthLfgTMbA/iGLC8i
t28CIDpKRJ3o/ky/K3SaSdv2iYtNRI2draZuDDVviDOXH8g3AiEAlFvAGW1yM+Ba
MCTAzggYlB3wyihbPBvDaHItwEtRxikCIQC2GzXRVDW6rbDJyX1Zhd/l7EC6heib
LErnxieVVzJglw==
-----END CERTIFICATE-----
EOD;

    protected const CERT_BASE64_HEX = <<<'EOD'
30820156020100300d06092a864886f70d0101010500048201403082013c020100024100e909d
4e5e5eef6e83062c4772dc1d5670a5a83e1a52f3012466417a1a5854d5553b1d9f162a153b38fb
fada56d374fc652d5d7f184cdf79544d72fc7a70c93810203010001024100dc4b57ffd041fb1479
91148a592e104f2ce16e2463e3dcbc99e04ada9b3f0d5f1b3f89c413a55a74c4a9585223b94299f8
3f2b748ffb04d50f9a04bb1ad93805022100f777bb47e21637b63d63727bd9995f77fecdb7802975e
ba56106a4dbe2ef6c0f022100f112bd7cd2ce045517b0fb5c1ac4190f80e1b612df81331b03f8862c2
f22b76f02203a4a449de8fe4cbf2b749a49dbf6898b4d448d9dada66e0c356f8833971fc83702210094
5bc0196d7233e05a3024c0ce0818941df0ca285b3c1bc368722dc04b51c629022100b61b35d15435baa
db0c9c97d5985dfe5ec40ba85e89b2c4ae7c6279557326097
EOD;

    public function testFromDbReturnsNullWhenNullIsPassed()
    {
        $this->assertNull($this->behavior()->retrieveProperty(null, static::COLUMN));
    }

    public function testFromDBTransformsPemToDer()
    {
        $this->assertSame(
            static::CERT,
            $this->behavior()->retrieveProperty(hex2bin(str_replace("\n", '', static::CERT_BASE64_HEX)), static::COLUMN)
        );
    }

    public function testToDbReturnsNullWhenNullIsPassed()
    {
        $this->assertNull($this->behavior()->persistProperty(null, static::COLUMN));
    }

    public function testToDbTransformsDerToPem()
    {
        $this->assertSame(
            hex2bin(str_replace("\n", '', static::CERT_BASE64_HEX)),
            $this->behavior()->persistProperty(static::CERT, static::COLUMN)
        );
    }

    protected function behavior(): DERBase64
    {
        return new DERBase64(['cert']);
    }
}
