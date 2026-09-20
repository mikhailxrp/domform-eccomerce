<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReserveTest extends TestCase
{
    // ─── isUniqueViolation() ─────────────────────────────────────────────

    public function testPdoExceptionWithIntegrityConstraintSqlStateIsUniqueViolation(): void
    {
        $e            = new PDOException('Duplicate entry', 0);
        $e->errorInfo = ['23000', 1062, "Duplicate entry '5' for key 'uq_reserves_active_variant'"];

        $this->assertTrue(isUniqueViolation($e));
    }

    public function testPdoExceptionWithSqlStateInCodeIsUniqueViolation(): void
    {
        // Без errorInfo драйвер отдаёт SQLSTATE строкой через getCode().
        $e = $this->pdoExceptionWithCode('23000');

        $this->assertTrue(isUniqueViolation($e));
    }

    public function testPdoExceptionWithOtherSqlStateIsNotUniqueViolation(): void
    {
        $e            = new PDOException('Table missing', 0);
        $e->errorInfo = ['42S02', 1146, "Table 'reserves' doesn't exist"];

        $this->assertFalse(isUniqueViolation($e));
    }

    public function testNonPdoExceptionIsNotUniqueViolation(): void
    {
        $this->assertFalse(isUniqueViolation(new RuntimeException('23000')));
    }

    // ─── reserveStatusLabel() ────────────────────────────────────────────

    public function testKnownStatusesHaveLabels(): void
    {
        $this->assertSame('Активен', reserveStatusLabel(RESERVE_STATUS_ACTIVE));
        $this->assertSame('Снят', reserveStatusLabel(RESERVE_STATUS_RELEASED));
        $this->assertSame('Списан', reserveStatusLabel(RESERVE_STATUS_FULFILLED));
    }

    public function testUnknownStatusFallsBackToRawValue(): void
    {
        $this->assertSame('bogus', reserveStatusLabel('bogus'));
    }

    /**
     * Драйвер MySQL кладёт в `PDOException::$code` строку SQLSTATE, а не
     * int, но конструктор принимает только int, а замыкание к внутреннему
     * классу привязать нельзя — единственный способ воспроизвести
     * поведение драйвера в тесте — подкласс, выставляющий `$code` сам.
     */
    private function pdoExceptionWithCode(string $sqlState): PDOException
    {
        return new class ($sqlState) extends PDOException {
            public function __construct(string $sqlState)
            {
                parent::__construct('Duplicate entry');
                $this->code = $sqlState;
            }
        };
    }
}
