<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CheckoutValidationTest extends TestCase
{
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name'               => 'Иван',
            'phone'              => '+79001234567',
            'email'              => '',
            'fulfillment_method' => FULFILLMENT_PICKUP,
            'address_city'       => '',
            'address_street'     => '',
            'address_house'      => '',
            'address_apartment'  => '',
            'address_comment'    => '',
            'save_address'       => false,
            'saved_address_id'   => null,
            'comment'            => 'Позвонить перед доставкой',
            'payment_method'     => PAYMENT_CASH,
            'create_account'     => false,
            'password'           => '',
        ], $overrides);
    }

    public function testPickupWithoutAddressIsValid(): void
    {
        $errors = validateCheckoutInput($this->validInput(), false);

        $this->assertFalse($errors['address_city']);
        $this->assertFalse($errors['address_street']);
        $this->assertFalse($errors['address_house']);
    }

    public function testDeliveryWithoutAddressIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput([
            'fulfillment_method' => FULFILLMENT_DELIVERY,
        ]), false);

        $this->assertTrue($errors['address_city']);
        $this->assertTrue($errors['address_street']);
        $this->assertTrue($errors['address_house']);
    }

    public function testDeliveryWithAddressIsValid(): void
    {
        $errors = validateCheckoutInput($this->validInput([
            'fulfillment_method' => FULFILLMENT_DELIVERY,
            'address_city'       => 'Краснодар',
            'address_street'     => 'Красная',
            'address_house'      => '10',
        ]), false);

        $this->assertFalse($errors['address_city']);
        $this->assertFalse($errors['address_street']);
        $this->assertFalse($errors['address_house']);
        $this->assertFalse($errors['address_apartment']);
        $this->assertFalse($errors['address_comment']);
    }

    public function testDeliveryWithTooLongApartmentIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput([
            'fulfillment_method' => FULFILLMENT_DELIVERY,
            'address_city'       => 'Краснодар',
            'address_street'     => 'Красная',
            'address_house'      => '10',
            'address_apartment'  => str_repeat('1', 21),
        ]), false);

        $this->assertTrue($errors['address_apartment']);
    }

    public function testEmptyEmailIsValidWithoutAccountCreation(): void
    {
        $errors = validateCheckoutInput($this->validInput(['email' => '']), false);

        $this->assertFalse($errors['email']);
    }

    public function testCreateAccountWithoutPasswordIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput([
            'email'          => 'guest@example.com',
            'create_account' => true,
            'password'       => '',
        ]), false);

        $this->assertTrue($errors['password']);
    }

    public function testCreateAccountWithPasswordIsValid(): void
    {
        $errors = validateCheckoutInput($this->validInput([
            'email'          => 'guest@example.com',
            'create_account' => true,
            'password'       => 'password123',
        ]), false);

        $this->assertFalse($errors['password']);
        $this->assertFalse($errors['email']);
    }

    public function testCreateAccountWithoutEmailIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput([
            'email'          => '',
            'create_account' => true,
            'password'       => 'password123',
        ]), false);

        $this->assertTrue($errors['email']);
    }

    public function testCreateAccountIgnoredForAuthenticatedUser(): void
    {
        $errors = validateCheckoutInput($this->validInput([
            'email'          => '',
            'create_account' => true,
            'password'       => '',
        ]), true);

        $this->assertFalse($errors['email']);
        $this->assertFalse($errors['password']);
    }

    public function testUnknownPaymentMethodIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput(['payment_method' => 'bitcoin']), false);

        $this->assertTrue($errors['payment_method']);
    }

    public function testUnknownFulfillmentMethodIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput(['fulfillment_method' => 'teleport']), false);

        $this->assertTrue($errors['fulfillment_method']);
    }

    public function testEmptyCommentIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput(['comment' => '']), false);

        $this->assertTrue($errors['comment']);
    }

    public function testEmptyNameIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput(['name' => '']), false);

        $this->assertTrue($errors['name']);
    }

    public function testInvalidPhoneIsInvalid(): void
    {
        $errors = validateCheckoutInput($this->validInput(['phone' => '123']), false);

        $this->assertTrue($errors['phone']);
    }

    public function testNormalizeCheckoutInputTrimsAndNormalizesPhone(): void
    {
        $normalized = normalizeCheckoutInput([
            'name'               => '  Иван  ',
            'phone'              => '8 900 123-45-67',
            'email'              => '  USER@EXAMPLE.COM ',
            'fulfillment_method' => FULFILLMENT_DELIVERY,
            'address_city'       => '  Краснодар  ',
            'address_street'     => '  Красная  ',
            'address_house'      => '  10  ',
            'address_apartment'  => '  5  ',
            'address_comment'    => '  домофон 25  ',
            'save_address'       => '1',
            'saved_address_id'   => '3',
            'comment'            => '  комментарий  ',
            'payment_method'     => PAYMENT_CARD_ONLINE,
            'create_account'     => '1',
            'password'           => 'secret123',
        ]);

        $this->assertSame('Иван', $normalized['name']);
        $this->assertSame('+79001234567', $normalized['phone']);
        $this->assertSame('user@example.com', $normalized['email']);
        $this->assertSame('Краснодар', $normalized['address_city']);
        $this->assertSame('Красная', $normalized['address_street']);
        $this->assertSame('10', $normalized['address_house']);
        $this->assertSame('5', $normalized['address_apartment']);
        $this->assertSame('домофон 25', $normalized['address_comment']);
        $this->assertTrue($normalized['save_address']);
        $this->assertSame(3, $normalized['saved_address_id']);
        $this->assertSame('комментарий', $normalized['comment']);
        $this->assertTrue($normalized['create_account']);
    }

    public function testNormalizeCheckoutInputMissingSavedAddressIdIsNull(): void
    {
        $normalized = normalizeCheckoutInput([
            'name'               => 'Иван',
            'phone'              => '+79001234567',
            'fulfillment_method' => FULFILLMENT_PICKUP,
            'comment'            => 'комментарий',
            'payment_method'     => PAYMENT_CASH,
        ]);

        $this->assertNull($normalized['saved_address_id']);
        $this->assertFalse($normalized['save_address']);
    }
}
