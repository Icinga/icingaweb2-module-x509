<?php

namespace Tests\Icinga\Modules\X509;

use Icinga\Module\X509\Job;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    public function testBinaryTransformsHumanReadableIPToItsPaddedVersionCorrectly()
    {
        $this->assertSame('0000000000000000000000000ad33720', bin2hex(Job::binary('10.211.55.32')));
        $this->assertSame(
            '2a0104a00004210208a951031cba4915',
            bin2hex(Job::binary('2a01:4a0:4:2102:8a9:5103:1cba:4915'))
        );
    }

    public function testIsIPV6()
    {
        $this->assertTrue(Job::isIPV6('::1'), 'Job::isIPV6() could not determine valid IPv6 as an IPv6');
        $this->assertFalse(Job::isIPV6('10.211.54.35'), 'Job::isIPV6() could determines IPv4 as an IPv6');
    }

    public function testAddrToNumberUndViceVersa()
    {
        $this->assertSame('10.211.55.32', Job::numberToAddr(Job::addrToNumber('10.211.55.32'), false));

        $this->assertSame('::1', Job::numberToAddr(Job::addrToNumber('::1')));
        $this->assertSame(
            '2a01:4a0:4:2102:8a9:5103:1cba:4915',
            Job::numberToAddr(Job::addrToNumber('2a01:4a0:4:2102:8a9:5103:1cba:4915'))
        );
    }

    public function testIsAddrInsideCidr()
    {
        $this->assertTrue(Job::isAddrInside(Job::addrToNumber('10.211.55.31'), '10.211.55.30', 24));
        $this->assertFalse(Job::isAddrInside(Job::addrToNumber('10.211.54.35'), '10.211.55.30', 24));

        $this->assertTrue(
            Job::isAddrInside(Job::addrToNumber('2001:db8:abcd:0012::1'), '2001:db8:abcd:0012::', 64)
        );
        $this->assertTrue(
            Job::isAddrInside(Job::addrToNumber('2001:db8:abcd:0012:ffff::1'), '2001:db8:abcd:0012::', 64)
        );

        $this->assertFalse(Job::isAddrInside(Job::addrToNumber('2001:db8:abcd::1'), '2001:db8:abcd:0012::', 64));
        $this->assertFalse(
            Job::isAddrInside(Job::addrToNumber('2001:db8:abcd:0011::'), '2001:db8:abcd:0012::', 64)
        );
    }
}
