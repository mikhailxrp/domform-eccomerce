<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WarrantyTest extends TestCase
{
    // ─── warrantyExpiresAt() ─────────────────────────────────────────────

    public function testNullDeliveredAtHasNoWarranty(): void
    {
        $this->assertNull(warrantyExpiresAt(null));
    }

    public function testOrdinaryDatePlusEighteenMonths(): void
    {
        $this->assertSame('2027-07-15', warrantyExpiresAt('2026-01-15'));
    }

    public function testEndOfMonthClampsToShorterTargetMonth(): void
    {
        // 31.12.2026 + 18 месяцев = июнь 2028 (30 дней), не «переполняется»
        // на июль, как это сделал бы наивный strtotime('+18 months').
        $this->assertSame('2028-06-30', warrantyExpiresAt('2026-12-31'));
    }

    public function testEndOfMonthClampsToLeapFebruary(): void
    {
        $this->assertSame('2028-02-29', warrantyExpiresAt('2026-08-31'));
    }

    public function testEndOfMonthClampsToNonLeapFebruary(): void
    {
        $this->assertSame('2029-02-28', warrantyExpiresAt('2027-08-31'));
    }

    // ─── isUnderWarranty() ───────────────────────────────────────────────

    public function testNotDeliveredIsNeverUnderWarranty(): void
    {
        $this->assertFalse(isUnderWarranty(null, '2026-01-01'));
    }

    public function testExactExpiryDayIsStillUnderWarranty(): void
    {
        $this->assertTrue(isUnderWarranty('2026-01-15', '2027-07-15'));
    }

    public function testDayAfterExpiryIsNotUnderWarranty(): void
    {
        $this->assertFalse(isUnderWarranty('2026-01-15', '2027-07-16'));
    }

    public function testDayBeforeExpiryIsUnderWarranty(): void
    {
        $this->assertTrue(isUnderWarranty('2026-01-15', '2027-07-14'));
    }
}
