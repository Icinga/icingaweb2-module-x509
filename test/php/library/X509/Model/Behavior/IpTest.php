<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Tests\Icinga\Modules\X509\Model\Behavior;

use Icinga\Module\X509\Model\Behavior\Ip;
use ipl\Orm\Query;
use ipl\Sql\Connection;
use PHPUnit\Framework\TestCase;

class IpTest extends TestCase
{
    protected const IPV4 = '10.211.55.32';

    protected const IPV6 = '2a01:4a0:4:2102:8a9:5103:1cba:4915';

    protected const IPV4_HEX = '0000000000000000000000000ad33720';

    protected const IPV6_HEX = '2a0104a00004210208a951031cba4915';

    protected const COLUMN = 'ip';

    public function testFromDbReturnsNullWhenNullIsPassed()
    {
        $this->assertNull($this->behavior()->retrieveProperty(null, static::COLUMN));
        $this->assertNull($this->behavior(true)->retrieveProperty(null, static::COLUMN));
    }

    public function testFromDBTransformsBinaryIpToHumanReadable()
    {
        $this->assertSame(
            static::IPV4,
            $this->behavior()->retrieveProperty(hex2bin(static::IPV4_HEX), static::COLUMN)
        );
        $this->assertSame(
            static::IPV6,
            $this->behavior()->retrieveProperty(hex2bin(static::IPV6_HEX), static::COLUMN)
        );

        $this->assertSame(
            static::IPV4,
            $this->behavior(true)->retrieveProperty(hex2bin(static::IPV4_HEX), static::COLUMN)
        );
        $this->assertSame(
            static::IPV6,
            $this->behavior(true)->retrieveProperty(hex2bin(static::IPV6_HEX), static::COLUMN)
        );
    }

    public function testToDbReturnsInvalidValueAsIs()
    {
        $this->assertNull($this->behavior()->persistProperty(null, static::COLUMN));
        $this->assertSame('*', $this->behavior()->persistProperty('*', static::COLUMN));

        $this->assertNull($this->behavior(true)->persistProperty(null, static::COLUMN));
        $this->assertSame('*', $this->behavior(true)->persistProperty('*', static::COLUMN));

        $ipv4Bin = hex2bin(static::IPV4_HEX);
        $ipv6Bin = hex2bin(static::IPV6_HEX);

        $this->assertSame($ipv4Bin, $this->behavior()->persistProperty($ipv4Bin, static::COLUMN));
        $this->assertSame($ipv6Bin, $this->behavior()->persistProperty($ipv6Bin, static::COLUMN));

        $this->assertSame($ipv4Bin, $this->behavior(true)->persistProperty($ipv4Bin, static::COLUMN));
        $this->assertSame($ipv6Bin, $this->behavior(true)->persistProperty($ipv6Bin, static::COLUMN));
    }

    public function testToDbTransformsIpToBinaryCorrectly()
    {
        $this->assertSame(hex2bin(static::IPV4_HEX), $this->behavior()->persistProperty(static::IPV4, static::COLUMN));
        $this->assertSame(hex2bin(static::IPV6_HEX), $this->behavior()->persistProperty(static::IPV6, static::COLUMN));

        $this->assertSame(
            sprintf('\\x%s', static::IPV4_HEX),
            $this->behavior(true)->persistProperty(static::IPV4, static::COLUMN)
        );
        $this->assertSame(
            sprintf('\\x%s', static::IPV6_HEX),
            $this->behavior(true)->persistProperty(static::IPV6, static::COLUMN)
        );
    }

    protected function behavior(bool $postgres = false): Ip
    {
        return (new Ip(['ip']))
            ->setQuery(
                (new Query())
                    ->setDb(new Connection(['db' => $postgres ? 'pgsql' : 'mysql']))
            );
    }
}
