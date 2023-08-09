<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Tests\Icinga\Modules\X509\Common;

use Icinga\Module\X509\Common\JobUtils;
use PHPUnit\Framework\TestCase;

class JobUtilsTest extends TestCase
{
    use JobUtils;

    public function testGetCidrs()
    {
        $cidrs = $this->parseCIDRs('10.211.55.30/24,127.0.0.1/8,192.168.178.1/28');

        $this->assertCount(3, $cidrs);
        $this->assertCount(2, $cidrs['10.211.55.30/24']);

        $this->assertSame('10.211.55.30', $cidrs['10.211.55.30/24'][0]);
        $this->assertSame('24', $cidrs['10.211.55.30/24'][1]);
    }

    public function testGetPorts()
    {
        $ports = $this->parsePorts('5665,3306,6379,8000-9000');

        $this->assertCount(4, $ports);
        $this->assertCount(2, $ports[3]);

        $this->assertSame('8000', $ports[3][0]);
        $this->assertSame('9000', $ports[3][1]);
    }

    public function testGetExcludes()
    {
        $excludes = $this->parseExcludes('icinga.com,netways.de');

        $this->assertCount(2, $excludes);
        $this->assertArrayHasKey('icinga.com', $excludes);
        $this->assertArrayHasKey('netways.de', $excludes);
    }
}
