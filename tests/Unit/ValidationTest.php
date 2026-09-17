<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testValidateEmailAcceptsCorrectAddress(): void
    {
        $this->assertTrue(validateEmail('user@example.com'));
    }

    public function testValidateEmailRejectsMalformedAddress(): void
    {
        $this->assertFalse(validateEmail('not-an-email'));
        $this->assertFalse(validateEmail('user@'));
        $this->assertFalse(validateEmail(''));
    }

    public function testValidateEmailRejectsTooLongAddress(): void
    {
        $local = str_repeat('a', 150);
        $this->assertFalse(validateEmail($local . '@example.com'));
    }

    public function testNormalizePhoneHandlesLeadingEight(): void
    {
        $this->assertSame('+79181234567', normalizePhone('89181234567'));
    }

    public function testNormalizePhoneHandlesLeadingSeven(): void
    {
        $this->assertSame('+79181234567', normalizePhone('79181234567'));
    }

    public function testNormalizePhoneHandlesLeadingPlusSeven(): void
    {
        $this->assertSame('+79181234567', normalizePhone('+7 918 123-45-67'));
    }

    public function testNormalizePhoneHandlesTenDigitsWithoutCountryCode(): void
    {
        $this->assertSame('+79181234567', normalizePhone('9181234567'));
    }

    public function testNormalizePhoneReturnsDigitsOnlyWhenUnrecognized(): void
    {
        $this->assertSame('123', normalizePhone('123'));
    }

    public function testValidatePhoneAcceptsNormalizableRussianNumber(): void
    {
        $this->assertTrue(validatePhone('8 (918) 123-45-67'));
    }

    public function testValidatePhoneRejectsTooShortNumber(): void
    {
        $this->assertFalse(validatePhone('12345'));
    }

    public function testValidatePhoneRejectsEmptyString(): void
    {
        $this->assertFalse(validatePhone(''));
    }

    public function testValidatePasswordAcceptsEightOrMoreCharacters(): void
    {
        $this->assertTrue(validatePassword('12345678'));
    }

    public function testValidatePasswordRejectsShorterThanEight(): void
    {
        $this->assertFalse(validatePassword('1234567'));
    }

    public function testValidatePasswordCountsMultibyteCharactersCorrectly(): void
    {
        $this->assertTrue(validatePassword('пароль12'));
    }
}
