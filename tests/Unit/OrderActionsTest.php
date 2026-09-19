<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OrderActionsTest extends TestCase
{
    // ─── validateCancelInput() ───────────────────────────────────────────

    public function testStandardBranchWithoutNoteIsValid(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_STANDARD, 'note' => '', 'refund_confirmed' => true],
            PAYMENT_STATUS_UNPAID
        );

        $this->assertNotContains(true, $errors);
    }

    public function testUnknownBranchIsInvalid(): void
    {
        $errors = validateCancelInput(
            ['branch' => 'bogus', 'note' => '', 'refund_confirmed' => true],
            PAYMENT_STATUS_UNPAID
        );

        $this->assertTrue($errors['branch']);
    }

    public function testMissingBranchIsInvalid(): void
    {
        $errors = validateCancelInput(['refund_confirmed' => true], PAYMENT_STATUS_UNPAID);

        $this->assertTrue($errors['branch']);
    }

    public function testNonStandardBranchWithoutNoteIsInvalid(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_NON_STANDARD, 'note' => '', 'refund_confirmed' => true],
            PAYMENT_STATUS_UNPAID
        );

        $this->assertTrue($errors['note']);
    }

    public function testNonStandardBranchWithNoteIsValid(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_NON_STANDARD, 'note' => 'Диван 3.2м вместо стандартных 2м', 'refund_confirmed' => true],
            PAYMENT_STATUS_UNPAID
        );

        $this->assertFalse($errors['note']);
    }

    public function testStandardBranchDoesNotRequireNote(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_STANDARD, 'note' => '', 'refund_confirmed' => true],
            PAYMENT_STATUS_UNPAID
        );

        $this->assertFalse($errors['note']);
    }

    public function testRefundNotRequiredWhenUnpaid(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_STANDARD, 'refund_confirmed' => false],
            PAYMENT_STATUS_UNPAID
        );

        $this->assertFalse($errors['refund_confirmed']);
    }

    public function testRefundRequiredWhenPrepaidAndNotConfirmed(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_STANDARD, 'refund_confirmed' => false],
            PAYMENT_STATUS_PREPAID
        );

        $this->assertTrue($errors['refund_confirmed']);
    }

    public function testRefundRequiredWhenPaidFullAndNotConfirmed(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_STANDARD, 'refund_confirmed' => false],
            PAYMENT_STATUS_PAID_FULL
        );

        $this->assertTrue($errors['refund_confirmed']);
    }

    public function testRefundConfirmedSatisfiesRequirement(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_STANDARD, 'refund_confirmed' => true],
            PAYMENT_STATUS_PREPAID
        );

        $this->assertFalse($errors['refund_confirmed']);
    }

    public function testAllValidReturnsNoTrueErrors(): void
    {
        $errors = validateCancelInput(
            ['branch' => CANCEL_BRANCH_NON_STANDARD, 'note' => 'Нестандартный размер', 'refund_confirmed' => true],
            PAYMENT_STATUS_PAID_FULL
        );

        $this->assertNotContains(true, $errors);
    }

    // ─── validateShippingCost() ──────────────────────────────────────────

    public function testValidShippingCostIsAccepted(): void
    {
        $this->assertNull(validateShippingCost('1500'));
        $this->assertNull(validateShippingCost('1500.50'));
        $this->assertNull(validateShippingCost('0'));
    }

    public function testMalformedShippingCostIsRejected(): void
    {
        $this->assertNotNull(validateShippingCost('-100'));
        $this->assertNotNull(validateShippingCost('12.345'));
        $this->assertNotNull(validateShippingCost('abc'));
        $this->assertNotNull(validateShippingCost(''));
    }

    // ─── canEditOrderItems() ─────────────────────────────────────────────

    public function testConfirmedAndInProductionAllowEditingItems(): void
    {
        $this->assertTrue(canEditOrderItems(ORDER_STATUS_CONFIRMED));
        $this->assertTrue(canEditOrderItems(ORDER_STATUS_IN_PRODUCTION));
    }

    public function testOtherStatusesDoNotAllowEditingItems(): void
    {
        $this->assertFalse(canEditOrderItems(ORDER_STATUS_NEW));
        $this->assertFalse(canEditOrderItems(ORDER_STATUS_READY_FOR_SHIPMENT));
        $this->assertFalse(canEditOrderItems(ORDER_STATUS_SHIPPING));
        $this->assertFalse(canEditOrderItems(ORDER_STATUS_DELIVERED));
        $this->assertFalse(canEditOrderItems(ORDER_STATUS_CANCELLED));
    }

    public function testUnknownStatusDoesNotAllowEditingItems(): void
    {
        $this->assertFalse(canEditOrderItems('bogus'));
    }
}
