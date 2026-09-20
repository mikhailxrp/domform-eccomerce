<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PriceTest extends TestCase
{
    // ─── discountedPrice() ───────────────────────────────────────────────

    public function testFifteenPercentDiscountOnRoundPrice(): void
    {
        $this->assertSame('34000.00', discountedPrice('40000.00', '15'));
    }

    public function testNullPercentReturnsPriceUnchanged(): void
    {
        $this->assertSame('40000.00', discountedPrice('40000.00', null));
    }

    public function testZeroPercentReturnsPriceUnchanged(): void
    {
        $this->assertSame('40000.00', discountedPrice('40000.00', '0'));
    }

    public function testZeroWithDecimalsPercentReturnsPriceUnchanged(): void
    {
        $this->assertSame('40000.00', discountedPrice('40000.00', '0.00'));
    }

    public function testMaxAllowedPercentNearlyZerosThePrice(): void
    {
        // discount_percent — DECIMAL(5,2), форма (Таск 8 Фазы 4)
        // допускает максимум 99.99.
        $this->assertSame('4.00', discountedPrice('40000.00', '99.99'));
    }

    public function testRoundsHalfUpOnThirdDecimal(): void
    {
        // 1999.99 * (100 - 33) / 100 = 1339.9933 → округление до копеек
        // вниз (третий знак 3 < 5), тот же результат, что MySQL ROUND()
        $this->assertSame('1339.99', discountedPrice('1999.99', '33'));
    }

    public function testRoundsHalfUpOnExactHalfCent(): void
    {
        // 99.99 * 50 / 100 = 49.995 ровно — половина копейки округляется
        // вверх, как ROUND() в MySQL для неотрицательных сумм
        $this->assertSame('50.00', discountedPrice('99.99', '50'));
    }

    // ─── hasDiscount() ───────────────────────────────────────────────────

    public function testHasDiscountIsFalseForNull(): void
    {
        $this->assertFalse(hasDiscount(null));
    }

    public function testHasDiscountIsFalseForZero(): void
    {
        $this->assertFalse(hasDiscount('0'));
    }

    public function testHasDiscountIsFalseForZeroWithDecimals(): void
    {
        $this->assertFalse(hasDiscount('0.00'));
    }

    public function testHasDiscountIsTrueForPositivePercent(): void
    {
        $this->assertTrue(hasDiscount('15'));
    }

    public function testHasDiscountIsTrueForSmallestPositivePercent(): void
    {
        $this->assertTrue(hasDiscount('0.01'));
    }

    // ─── discountedPriceSql() ────────────────────────────────────────────

    public function testDiscountedPriceSqlUsesGivenAlias(): void
    {
        $this->assertSame(
            'ROUND(pv.price * (1 - IFNULL(pv.discount_percent, 0) / 100), 2)',
            discountedPriceSql('pv')
        );
    }

    public function testDiscountedPriceSqlWithDifferentAlias(): void
    {
        $this->assertSame(
            'ROUND(pv3.price * (1 - IFNULL(pv3.discount_percent, 0) / 100), 2)',
            discountedPriceSql('pv3')
        );
    }
}
