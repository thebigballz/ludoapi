<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_converts_decimal_amounts_to_minor_units(): void
    {
        $this->assertSame(10000, Money::toMinor('100.00'));
        $this->assertSame(2550, Money::toMinor('25.5'));
        $this->assertSame(7, Money::toMinor('0.07'));
    }

    public function test_it_handles_negative_amounts_without_float_arithmetic(): void
    {
        $this->assertSame(-1250, Money::toMinor('-12.50'));
        $this->assertSame('-12.50', Money::fromMinor(-1250));
    }

    public function test_it_formats_minor_units_exactly(): void
    {
        $this->assertSame('100.00', Money::fromMinor(10000));
        $this->assertSame('25.50', Money::fromMinor(2550));
        $this->assertSame('0.07', Money::fromMinor(7));
    }

    public function test_it_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toMinor('10.005');
    }

    public function test_it_rejects_non_positive_transaction_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::assertPositive('0.00');
    }
}
