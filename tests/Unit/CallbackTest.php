<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CallbackTest extends TestCase
{
    // ─── normalizeCallbackInput() ───────────────────────────────────────

    public function testNormalizeTrimsNameAndComment(): void
    {
        $normalized = normalizeCallbackInput([
            'name'    => '  Иван  ',
            'phone'   => '+7 900 123-45-67',
            'comment' => '  перезвоните вечером  ',
        ]);

        $this->assertSame('Иван', $normalized['name']);
        $this->assertSame('+79001234567', $normalized['phone']);
        $this->assertSame('перезвоните вечером', $normalized['comment']);
    }

    public function testNormalizeMissingCommentIsEmptyString(): void
    {
        $normalized = normalizeCallbackInput(['name' => 'Иван', 'phone' => '+79001234567']);

        $this->assertSame('', $normalized['comment']);
    }

    // ─── validateCallbackInput() ─────────────────────────────────────────

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name'    => 'Иван',
            'phone'   => '+79001234567',
            'comment' => 'Перезвоните после 18:00',
        ], $overrides);
    }

    public function testValidInputHasNoErrors(): void
    {
        $errors = validateCallbackInput($this->validInput());

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testEmptyCommentIsValid(): void
    {
        $errors = validateCallbackInput($this->validInput(['comment' => '']));

        $this->assertFalse($errors['comment']);
    }

    public function testNameShorterThanTwoCharsIsInvalid(): void
    {
        $errors = validateCallbackInput($this->validInput(['name' => 'И']));

        $this->assertTrue($errors['name']);
    }

    public function testNameLongerThan150CharsIsInvalid(): void
    {
        $errors = validateCallbackInput($this->validInput(['name' => str_repeat('а', 151)]));

        $this->assertTrue($errors['name']);
    }

    public function testNameExactly150CharsIsValid(): void
    {
        $errors = validateCallbackInput($this->validInput(['name' => str_repeat('а', 150)]));

        $this->assertFalse($errors['name']);
    }

    public function testInvalidPhoneIsInvalid(): void
    {
        $errors = validateCallbackInput($this->validInput(['phone' => '123']));

        $this->assertTrue($errors['phone']);
    }

    public function testCommentOverLimitIsInvalid(): void
    {
        $errors = validateCallbackInput($this->validInput(['comment' => str_repeat('а', 501)]));

        $this->assertTrue($errors['comment']);
    }

    public function testCommentExactlyAtLimitIsValid(): void
    {
        $errors = validateCallbackInput($this->validInput(['comment' => str_repeat('а', 500)]));

        $this->assertFalse($errors['comment']);
    }
}
