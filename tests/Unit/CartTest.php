<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase
{
    // ─── clampCartQuantity() ────────────────────────────────────────────

    public function testClampKeepsValidQuantity(): void
    {
        $this->assertSame(5, clampCartQuantity(5, false));
    }

    public function testClampRaisesZeroToOne(): void
    {
        $this->assertSame(1, clampCartQuantity(0, false));
    }

    public function testClampRaisesNegativeToOne(): void
    {
        $this->assertSame(1, clampCartQuantity(-10, false));
    }

    public function testClampCapsAboveMax(): void
    {
        $this->assertSame(99, clampCartQuantity(500, false));
    }

    public function testClampShowroomSampleIsAlwaysOne(): void
    {
        $this->assertSame(1, clampCartQuantity(5, true));
        $this->assertSame(1, clampCartQuantity(0, true));
        $this->assertSame(1, clampCartQuantity(500, true));
    }

    // ─── calculateCartTotals() ──────────────────────────────────────────

    public function testCalculateCartTotalsSumsWithoutFloat(): void
    {
        $result = calculateCartTotals([
            ['price' => '1999.99', 'quantity' => 3, 'price_snapshot' => '1999.99', 'is_reserved' => false],
        ]);

        $this->assertSame('5999.97', $result['items'][0]['line_total']);
        $this->assertSame('5999.97', $result['total']);
        $this->assertTrue($result['items'][0]['available']);
    }

    public function testCalculateCartTotalsSumsMultipleLines(): void
    {
        $result = calculateCartTotals([
            ['price' => '1000.00', 'quantity' => 2, 'price_snapshot' => '1000.00', 'is_reserved' => false],
            ['price' => '500.50',  'quantity' => 1, 'price_snapshot' => '500.50',  'is_reserved' => false],
        ]);

        $this->assertSame('2500.50', $result['total']);
    }

    public function testCalculateCartTotalsExcludesReservedItemFromTotal(): void
    {
        $result = calculateCartTotals([
            ['price' => '1000.00', 'quantity' => 1, 'price_snapshot' => '1000.00', 'is_reserved' => true],
            ['price' => '500.00',  'quantity' => 1, 'price_snapshot' => '500.00',  'is_reserved' => false],
        ]);

        $this->assertSame('0.00', $result['items'][0]['line_total']);
        $this->assertFalse($result['items'][0]['available']);
        $this->assertSame('500.00', $result['total']);
    }

    public function testCalculateCartTotalsEmptyCartIsZero(): void
    {
        $result = calculateCartTotals([]);

        $this->assertSame([], $result['items']);
        $this->assertSame('0.00', $result['total']);
    }

    // ─── findPriceChanges() ─────────────────────────────────────────────

    public function testFindPriceChangesDetectsIncrease(): void
    {
        $changed = findPriceChanges([
            ['price' => '2500.00', 'price_snapshot' => '1999.99'],
        ]);

        $this->assertCount(1, $changed);
    }

    public function testFindPriceChangesDetectsDecrease(): void
    {
        $changed = findPriceChanges([
            ['price' => '1500.00', 'price_snapshot' => '1999.99'],
        ]);

        $this->assertCount(1, $changed);
    }

    public function testFindPriceChangesIgnoresUnchangedPrice(): void
    {
        $changed = findPriceChanges([
            ['price' => '1999.99', 'price_snapshot' => '1999.99'],
        ]);

        $this->assertSame([], $changed);
    }

    public function testFindPriceChangesIgnoresFormattingDifferences(): void
    {
        $changed = findPriceChanges([
            ['price' => '1999.9', 'price_snapshot' => '1999.90'],
        ]);

        $this->assertSame([], $changed);
    }

    public function testFindPriceChangesOnMixedCart(): void
    {
        $changed = findPriceChanges([
            ['price' => '1000.00', 'price_snapshot' => '1000.00'],
            ['price' => '2000.00', 'price_snapshot' => '1500.00'],
        ]);

        $this->assertCount(1, $changed);
    }
}
