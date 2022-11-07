<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Tests\Icinga\Modules\X509;

use Icinga\Data\ConfigObject;
use Icinga\Module\X509\Common\JobUtils;
use PHPUnit\Framework\TestCase;

class JobUtilsTest extends TestCase
{
    use JobUtils;

    public const CIDRS = '10.211.55.30/24,127.0.0.1/8,192.168.178.1';
    public const PORTS = '5665,3306,6379,8000-9000';

    protected function setUp(): void
    {
        parent::setUp();

        $config = new ConfigObject([
            'cidrs' => self::CIDRS,
            'ports' => self::PORTS
        ]);

        $this->setJobConfig($config);
    }

    public function testGetCidrs()
    {
        $cidrs = $this->getCidrs();

        $this->assertCount(2, $cidrs, 'Job::getCidrs() failed to parse comma-separated cidrs correctly');
        $this->assertCount(2, $cidrs['10.211.55.30/24'], 'Job::getCidrs() could not parse a cidr correctly');
    }

    public function testGetPorts()
    {
        $ports = $this->getPorts();

        $this->assertCount(4, $ports, 'Job::getPorts() failed to parse comma-separated ports correctly');
        $this->assertCount(2, $ports[3], 'Job::getPorts() could not parse port ranges correctly');

        $this->assertEquals('8000', $ports[3][0], 'Job::getPorts() could not return expected result');
        $this->assertEquals('9000', $ports[3][1], 'Job::getPorts() could not return expected result');
    }

    public function testIsAddrInsideCidr()
    {
        $cidr = ['10.211.55.30', '24'];
        $this->assertTrue(
            $this->isAddrInside($cidr, gmp_import(self::binary('10.211.55.31'))),
            'JobUtils::isAddrInside() could not determine whether an IP is inside a CIDR'
        );

        $this->assertTrue(
            $this->isAddrInside($cidr, gmp_import(self::binary('10.211.54.35'))),
            'JobUtils::isAddrInside() could not determine whether an IP is not a part of a CIDR'
        );
    }
}
