<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function testZeroAmountIsInvalid(): void
    {
        $this->assertNotNull(validatePaymentAmount('0', '35000.00', '0.00'));
    }

    public function testNegativeAmountIsInvalid(): void
    {
        $this->assertNotNull(validatePaymentAmount('-100', '35000.00', '0.00'));
    }

    public function testAmountAboveRemainingIsInvalid(): void
    {
        $this->assertNotNull(validatePaymentAmount('20000.00', '35000.00', '17500.00'));
    }

    public function testAmountEqualToRemainingIsValid(): void
    {
        $this->assertNull(validatePaymentAmount('17500.00', '35000.00', '17500.00'));
    }

    public function testValidAmountWithinRemainingIsValid(): void
    {
        $this->assertNull(validatePaymentAmount('10500.00', '35000.00', '0.00'));
    }

    public function testMalformedAmountIsInvalid(): void
    {
        $this->assertNotNull(validatePaymentAmount('10.999', '35000.00', '0.00'));
        $this->assertNotNull(validatePaymentAmount('abc', '35000.00', '0.00'));
        $this->assertNotNull(validatePaymentAmount('', '35000.00', '0.00'));
    }
}
