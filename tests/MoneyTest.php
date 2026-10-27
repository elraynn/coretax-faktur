<?php

namespace Elrayn\CoretaxFaktur\Tests;

use Elrayn\CoretaxFaktur\Money;
use Elrayn\CoretaxFaktur\Rounding;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testToCentsAcceptsIntsFloatsAndStrings(): void
    {
        $this->assertSame(100000000, Money::toCents(1000000));
        $this->assertSame(90090090, Money::toCents(900900.9));
        $this->assertSame(8738738730, Money::toCents('87387387.3'));
        $this->assertSame(-150, Money::toCents('-1.5'));
    }

    public function testToCentsRoundsHalfUpAtTheSecondDecimal(): void
    {
        $this->assertSame(1, Money::toCents('0.005'));
        $this->assertSame(0, Money::toCents('0.0049'));
        $this->assertSame(1235, Money::toCents('12.345'));
    }

    public function testToCentsRejectsThousandSeparators(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toCents('1,000,000');
    }

    public function testFormatDropsTrailingZeros(): void
    {
        $this->assertSame('1000000', Money::format(100000000));
        $this->assertSame('87387387.3', Money::format(8738738730));
        $this->assertSame('80105105.03', Money::format(8010510503));
        $this->assertSame('0', Money::format(0));
    }

    public function testDivideRoundsHalfUpOrHalfEven(): void
    {
        $this->assertSame(3911, Money::divide(39105, 10, Rounding::HALF_UP));
        $this->assertSame(3910, Money::divide(39105, 10, Rounding::HALF_EVEN));
        $this->assertSame(3912, Money::divide(39115, 10, Rounding::HALF_EVEN));
        $this->assertSame(3910, Money::divide(39104, 10, Rounding::HALF_UP));
    }
}
