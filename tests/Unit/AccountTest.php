<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    // ─── validateProfileInput() ──────────────────────────────────────────

    private function validProfileInput(array $overrides = []): array
    {
        return array_merge([
            'name'             => 'Иван Иванов',
            'email'            => 'ivan@example.com',
            'current_password' => '',
        ], $overrides);
    }

    public function testProfileValidWhenEmailUnchangedAndNoPassword(): void
    {
        $errors = validateProfileInput($this->validProfileInput(), 'ivan@example.com');

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testProfileEmailChangeWithoutPasswordIsInvalid(): void
    {
        $errors = validateProfileInput(
            $this->validProfileInput(['email' => 'new@example.com']),
            'ivan@example.com'
        );

        $this->assertTrue($errors['current_password']);
    }

    public function testProfileEmailChangeWithPasswordPassesStructuralCheck(): void
    {
        $errors = validateProfileInput(
            $this->validProfileInput(['email' => 'new@example.com', 'current_password' => 'secret123']),
            'ivan@example.com'
        );

        $this->assertFalse($errors['current_password']);
    }

    public function testProfileEmailComparisonIsCaseInsensitive(): void
    {
        $errors = validateProfileInput(
            $this->validProfileInput(['email' => 'Ivan@Example.COM']),
            'ivan@example.com'
        );

        $this->assertFalse($errors['current_password']);
    }

    public function testProfileEmptyNameIsInvalid(): void
    {
        $errors = validateProfileInput($this->validProfileInput(['name' => ' ']), 'ivan@example.com');

        $this->assertTrue($errors['name']);
    }

    public function testProfileTooLongNameIsInvalid(): void
    {
        $errors = validateProfileInput(
            $this->validProfileInput(['name' => str_repeat('А', 101)]),
            'ivan@example.com'
        );

        $this->assertTrue($errors['name']);
    }

    public function testProfileInvalidEmailFormatIsInvalid(): void
    {
        $errors = validateProfileInput($this->validProfileInput(['email' => 'not-an-email']), 'ivan@example.com');

        $this->assertTrue($errors['email']);
    }

    // ─── validatePasswordChangeInput() ──────────────────────────────────

    private function validPasswordInput(array $overrides = []): array
    {
        return array_merge([
            'current_password'     => 'oldPass123',
            'new_password'         => 'newPass456',
            'new_password_confirm' => 'newPass456',
        ], $overrides);
    }

    public function testPasswordChangeValidInputHasNoErrors(): void
    {
        $errors = validatePasswordChangeInput($this->validPasswordInput());

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testPasswordChangeEmptyCurrentPasswordIsInvalid(): void
    {
        $errors = validatePasswordChangeInput($this->validPasswordInput(['current_password' => '']));

        $this->assertTrue($errors['current_password']);
    }

    public function testPasswordChangeTooShortNewPasswordIsInvalid(): void
    {
        $errors = validatePasswordChangeInput($this->validPasswordInput(['new_password' => 'short']));

        $this->assertTrue($errors['new_password']);
    }

    public function testPasswordChangeSameAsCurrentIsInvalid(): void
    {
        $errors = validatePasswordChangeInput($this->validPasswordInput([
            'current_password' => 'samePass123',
            'new_password'     => 'samePass123',
            'new_password_confirm' => 'samePass123',
        ]));

        $this->assertTrue($errors['new_password']);
    }

    public function testPasswordChangeMismatchedConfirmationIsInvalid(): void
    {
        $errors = validatePasswordChangeInput($this->validPasswordInput(['new_password_confirm' => 'different']));

        $this->assertTrue($errors['new_password_confirm']);
    }
}
