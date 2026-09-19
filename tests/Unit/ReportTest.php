<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-19');
    }

    public function testTodayPreset(): void
    {
        $period = resolveReportPeriod('today', null, null, $this->now());

        $this->assertNull($period['error']);
        $this->assertSame('2026-09-19', $period['from']);
        $this->assertSame('2026-09-19', $period['to']);
    }

    public function testWeekPresetIsSevenDayWindow(): void
    {
        $period = resolveReportPeriod('week', null, null, $this->now());

        $this->assertNull($period['error']);
        $this->assertSame('2026-09-13', $period['from']);
        $this->assertSame('2026-09-19', $period['to']);
    }

    public function testMonthPresetIsThirtyDayWindow(): void
    {
        $period = resolveReportPeriod('month', null, null, $this->now());

        $this->assertNull($period['error']);
        $this->assertSame('2026-08-21', $period['from']);
        $this->assertSame('2026-09-19', $period['to']);
    }

    public function testCustomPeriodWithValidDates(): void
    {
        $period = resolveReportPeriod('custom', '2026-01-01', '2026-01-31', $this->now());

        $this->assertNull($period['error']);
        $this->assertSame('2026-01-01', $period['from']);
        $this->assertSame('2026-01-31', $period['to']);
    }

    public function testCustomPeriodRequiresBothDates(): void
    {
        $period = resolveReportPeriod('custom', '2026-01-01', null, $this->now());

        $this->assertNotNull($period['error']);
        $this->assertNull($period['from']);
        $this->assertNull($period['to']);
    }

    public function testCustomPeriodRejectsGarbageDate(): void
    {
        $period = resolveReportPeriod('custom', 'not-a-date', '2026-01-31', $this->now());

        $this->assertNotNull($period['error']);
    }

    public function testCustomPeriodRejectsOverflowingDate(): void
    {
        // 13-й месяц — createFromFormat молча перекатывает, не должно пройти.
        $period = resolveReportPeriod('custom', '2026-13-45', '2026-01-31', $this->now());

        $this->assertNotNull($period['error']);
    }

    public function testCustomPeriodRejectsFromAfterTo(): void
    {
        $period = resolveReportPeriod('custom', '2026-02-01', '2026-01-01', $this->now());

        $this->assertNotNull($period['error']);
    }

    public function testCustomPeriodRejectsRangeOverLimit(): void
    {
        $period = resolveReportPeriod('custom', '2020-01-01', '2026-01-01', $this->now());

        $this->assertNotNull($period['error']);
    }

    public function testCustomPeriodAcceptsRangeAtLimit(): void
    {
        $from   = new DateTimeImmutable('2026-01-01');
        $to     = $from->modify('+' . REPORT_MAX_RANGE_DAYS . ' days');
        $period = resolveReportPeriod('custom', $from->format('Y-m-d'), $to->format('Y-m-d'), $this->now());

        $this->assertNull($period['error']);
    }

    public function testUnknownPresetIsRejected(): void
    {
        $period = resolveReportPeriod('bogus', null, null, $this->now());

        $this->assertNotNull($period['error']);
        $this->assertNull($period['from']);
        $this->assertNull($period['to']);
    }
}
