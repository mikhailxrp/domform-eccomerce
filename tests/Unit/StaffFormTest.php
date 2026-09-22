<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StaffFormTest extends TestCase
{
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name'     => 'Иван Иванов',
            'email'    => 'ivan@example.com',
            'phone'    => '',
            'password' => 'password123',
            'role'     => 'manager',
        ], $overrides);
    }

    public function testValidInputHasNoErrors(): void
    {
        $errors = validateStaffInput($this->validInput());

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testEmptyNameIsInvalid(): void
    {
        $errors = validateStaffInput($this->validInput(['name' => '']));

        $this->assertTrue($errors['name']);
    }

    public function testBlankNameIsInvalid(): void
    {
        $errors = validateStaffInput($this->validInput(['name' => '   ']));

        $this->assertTrue($errors['name']);
    }

    public function testInvalidEmailIsInvalid(): void
    {
        $errors = validateStaffInput($this->validInput(['email' => 'not-an-email']));

        $this->assertTrue($errors['email']);
    }

    public function testEmptyPhoneIsValid(): void
    {
        $errors = validateStaffInput($this->validInput(['phone' => '']));

        $this->assertFalse($errors['phone']);
    }

    public function testValidPhoneIsValid(): void
    {
        $errors = validateStaffInput($this->validInput(['phone' => '+7 900 123-45-67']));

        $this->assertFalse($errors['phone']);
    }

    public function testInvalidPhoneIsInvalid(): void
    {
        $errors = validateStaffInput($this->validInput(['phone' => '123']));

        $this->assertTrue($errors['phone']);
    }

    public function testPasswordShorterThan8CharsIsInvalid(): void
    {
        $errors = validateStaffInput($this->validInput(['password' => 'short1']));

        $this->assertTrue($errors['password']);
    }

    public function testPasswordExactly8CharsIsValid(): void
    {
        $errors = validateStaffInput($this->validInput(['password' => '12345678']));

        $this->assertFalse($errors['password']);
    }

    public function testManagerRoleIsValid(): void
    {
        $errors = validateStaffInput($this->validInput(['role' => 'manager']));

        $this->assertFalse($errors['role']);
    }

    public function testAdminRoleIsValid(): void
    {
        $errors = validateStaffInput($this->validInput(['role' => 'admin']));

        $this->assertFalse($errors['role']);
    }

    public function testCustomerRoleIsInvalid(): void
    {
        $errors = validateStaffInput($this->validInput(['role' => 'customer']));

        $this->assertTrue($errors['role']);
    }

    public function testEmptyRoleIsInvalid(): void
    {
        $errors = validateStaffInput($this->validInput(['role' => '']));

        $this->assertTrue($errors['role']);
    }
}
