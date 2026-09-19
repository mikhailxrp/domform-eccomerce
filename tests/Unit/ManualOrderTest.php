<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ManualOrderTest extends TestCase
{
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'customer_mode'      => MANUAL_ORDER_CUSTOMER_GUEST,
            'user_id'            => 0,
            'guest_name'         => 'Иван',
            'guest_phone'        => '+79001234567',
            'guest_email'        => '',
            'fulfillment_method' => FULFILLMENT_PICKUP,
            'delivery_address'   => '',
            'payment_method'     => PAYMENT_CASH,
            'comment'            => '',
            'prepaid_amount'     => '5000',
            'items'              => [['sku' => 'SKU-1', 'variant_id' => 1, 'color' => '', 'quantity' => 1]],
        ], $overrides);
    }

    // ─── validateManualOrderInput() — контакты ────────────────────────────

    public function testGuestModeRequiresNameAndPhone(): void
    {
        $errors = validateManualOrderInput($this->validInput(['guest_name' => '', 'guest_phone' => '']));

        $this->assertTrue($errors['guest_name']);
        $this->assertTrue($errors['guest_phone']);
    }

    public function testGuestModeWithValidContactsIsValid(): void
    {
        $errors = validateManualOrderInput($this->validInput());

        $this->assertFalse($errors['guest_name']);
        $this->assertFalse($errors['guest_phone']);
    }

    public function testInvalidGuestPhoneIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['guest_phone' => '123']));

        $this->assertTrue($errors['guest_phone']);
    }

    public function testUserModeDoesNotRequireGuestContacts(): void
    {
        $errors = validateManualOrderInput($this->validInput([
            'customer_mode' => MANUAL_ORDER_CUSTOMER_USER,
            'user_id'       => 42,
            'guest_name'    => '',
            'guest_phone'   => '',
        ]));

        $this->assertFalse($errors['guest_name']);
        $this->assertFalse($errors['guest_phone']);
    }

    public function testEmptyGuestEmailIsValid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['guest_email' => '']));

        $this->assertFalse($errors['guest_email']);
    }

    public function testMalformedGuestEmailIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['guest_email' => 'not-an-email']));

        $this->assertTrue($errors['guest_email']);
    }

    // ─── fulfillment / payment / delivery address ─────────────────────────

    public function testUnknownFulfillmentMethodIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['fulfillment_method' => 'teleport']));

        $this->assertTrue($errors['fulfillment_method']);
    }

    public function testDeliveryWithoutAddressIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput([
            'fulfillment_method' => FULFILLMENT_DELIVERY,
            'delivery_address'   => '',
        ]));

        $this->assertTrue($errors['delivery_address']);
    }

    public function testDeliveryWithAddressIsValid(): void
    {
        $errors = validateManualOrderInput($this->validInput([
            'fulfillment_method' => FULFILLMENT_DELIVERY,
            'delivery_address'   => 'ул. Ленина, 1',
        ]));

        $this->assertFalse($errors['delivery_address']);
    }

    public function testUnknownPaymentMethodIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['payment_method' => 'bitcoin']));

        $this->assertTrue($errors['payment_method']);
    }

    // ─── prepaid_amount ────────────────────────────────────────────────

    public function testZeroPrepaidAmountIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['prepaid_amount' => '0']));

        $this->assertTrue($errors['prepaid_amount']);
    }

    public function testNegativePrepaidAmountIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['prepaid_amount' => '-100']));

        $this->assertTrue($errors['prepaid_amount']);
    }

    public function testMalformedPrepaidAmountIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['prepaid_amount' => '12.345']));

        $this->assertTrue($errors['prepaid_amount']);
    }

    public function testValidPrepaidAmountIsValid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['prepaid_amount' => '10500.50']));

        $this->assertFalse($errors['prepaid_amount']);
    }

    // ─── items ─────────────────────────────────────────────────────────

    public function testEmptyItemsIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput(['items' => []]));

        $this->assertTrue($errors['items']);
    }

    public function testUnresolvedVariantIdIsInvalid(): void
    {
        $errors = validateManualOrderInput($this->validInput([
            'items' => [['sku' => 'UNKNOWN', 'variant_id' => 0, 'color' => '', 'quantity' => 1]],
        ]));

        $this->assertTrue($errors['items']);
    }

    public function testAllResolvedItemsAreValid(): void
    {
        $errors = validateManualOrderInput($this->validInput([
            'items' => [
                ['sku' => 'SKU-1', 'variant_id' => 1, 'color' => '', 'quantity' => 1],
                ['sku' => 'SKU-2', 'variant_id' => 2, 'color' => 'Белый', 'quantity' => 2],
            ],
        ]));

        $this->assertFalse($errors['items']);
    }

    public function testAllValidReturnsNoTrueErrors(): void
    {
        $errors = validateManualOrderInput($this->validInput());

        $this->assertNotContains(true, $errors);
    }

    // ─── normalizeManualOrderInput() ────────────────────────────────────

    public function testNormalizeManualOrderInputTrimsAndNormalizesPhone(): void
    {
        $normalized = normalizeManualOrderInput([
            'customer_mode'      => 'guest',
            'guest_name'         => '  Иван  ',
            'guest_phone'        => '8 900 123-45-67',
            'guest_email'        => '  USER@EXAMPLE.COM ',
            'fulfillment_method' => ' delivery ',
            'delivery_address'   => '  ул. Ленина, 1  ',
            'payment_method'     => ' cash ',
            'comment'            => '  комментарий  ',
            'prepaid_amount'     => ' 5000 ',
            'items'              => [],
        ]);

        $this->assertSame('Иван', $normalized['guest_name']);
        $this->assertSame('+79001234567', $normalized['guest_phone']);
        $this->assertSame('user@example.com', $normalized['guest_email']);
        $this->assertSame('ул. Ленина, 1', $normalized['delivery_address']);
        $this->assertSame('комментарий', $normalized['comment']);
        $this->assertSame('5000', $normalized['prepaid_amount']);
    }

    public function testNormalizeManualOrderInputDefaultsUnknownCustomerModeToGuest(): void
    {
        $normalized = normalizeManualOrderInput(['customer_mode' => 'bogus']);

        $this->assertSame(MANUAL_ORDER_CUSTOMER_GUEST, $normalized['customer_mode']);
    }

    public function testNormalizeManualOrderInputAcceptsUserMode(): void
    {
        $normalized = normalizeManualOrderInput(['customer_mode' => 'user', 'user_id' => '7']);

        $this->assertSame(MANUAL_ORDER_CUSTOMER_USER, $normalized['customer_mode']);
        $this->assertSame(7, $normalized['user_id']);
    }

    // ─── normalizeManualOrderItems() ────────────────────────────────────

    public function testNormalizeManualOrderItemsDropsBlankRows(): void
    {
        $items = normalizeManualOrderItems([
            ['sku' => '', 'variant_id' => 0, 'color' => '', 'quantity' => 1],
            ['sku' => 'SKU-1', 'variant_id' => 0, 'color' => 'Белый', 'quantity' => 2],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('SKU-1', $items[0]['sku']);
        $this->assertSame(2, $items[0]['quantity']);
    }

    public function testNormalizeManualOrderItemsClampsQuantityToAtLeastOne(): void
    {
        $items = normalizeManualOrderItems([
            ['sku' => 'SKU-1', 'variant_id' => 1, 'color' => '', 'quantity' => 0],
        ]);

        $this->assertSame(1, $items[0]['quantity']);
    }

    public function testNormalizeManualOrderItemsKeepsRowWithOnlyVariantId(): void
    {
        $items = normalizeManualOrderItems([
            ['sku' => '', 'variant_id' => 5, 'color' => '', 'quantity' => 1],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame(5, $items[0]['variant_id']);
    }
}
