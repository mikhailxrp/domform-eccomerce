<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ApiAuthTest extends TestCase
{
    // ─── generateApiKey() / isValidApiKeyFormat() ───────────────────────────

    public function testGeneratedKeyHasValidFormat(): void
    {
        $this->assertTrue(isValidApiKeyFormat(generateApiKey()));
    }

    public function testGeneratedKeysDiffer(): void
    {
        $this->assertNotSame(generateApiKey(), generateApiKey());
    }

    public function testKeyFormatRejectsGarbage(): void
    {
        $this->assertFalse(isValidApiKeyFormat(''));
        $this->assertFalse(isValidApiKeyFormat('dfk_short'));
        $this->assertFalse(isValidApiKeyFormat('xxx_' . str_repeat('a', 32)));
        $this->assertFalse(isValidApiKeyFormat('dfk_' . str_repeat('A', 32)));
        $this->assertFalse(isValidApiKeyFormat("dfk_" . str_repeat('a', 32) . "\n"));
    }

    // ─── normalizeOrigin() ──────────────────────────────────────────────────

    public function testOriginIsLowercasedAndTrimmed(): void
    {
        $this->assertSame('https://example.com', normalizeOrigin('  HTTPS://Example.COM/ '));
    }

    public function testDefaultPortIsDropped(): void
    {
        $this->assertSame('https://example.com', normalizeOrigin('https://example.com:443'));
        $this->assertSame('http://example.com', normalizeOrigin('http://example.com:80'));
    }

    public function testNonDefaultPortIsKept(): void
    {
        $this->assertSame('http://localhost:8080', normalizeOrigin('http://localhost:8080'));
    }

    public function testInvalidOriginsAreRejected(): void
    {
        $this->assertNull(normalizeOrigin(''));
        $this->assertNull(normalizeOrigin('null'));
        $this->assertNull(normalizeOrigin('example.com'));
        $this->assertNull(normalizeOrigin('ftp://example.com'));
        $this->assertNull(normalizeOrigin('javascript:alert(1)'));
        $this->assertNull(normalizeOrigin('https://example.com/path'));
        $this->assertNull(normalizeOrigin('https://example.com?x=1'));
        $this->assertNull(normalizeOrigin('https://user:pass@example.com'));
    }

    // ─── parseAllowedOrigins() / isOriginAllowed() ──────────────────────────

    public function testParseSplitsOnNewlinesCommasAndSpaces(): void
    {
        $result = parseAllowedOrigins("https://a.ru\nhttps://b.ru, https://c.ru");

        $this->assertSame(['https://a.ru', 'https://b.ru', 'https://c.ru'], $result);
    }

    public function testParseDropsInvalidAndDuplicates(): void
    {
        $result = parseAllowedOrigins("https://a.ru\nмусор\nhttps://A.ru/\nhttps://a.ru");

        $this->assertSame(['https://a.ru'], $result);
    }

    public function testParseOfEmptyStringIsEmpty(): void
    {
        $this->assertSame([], parseAllowedOrigins('  '));
    }

    public function testAllowedOriginMatchesExactly(): void
    {
        $allowed = ['https://a.ru'];

        $this->assertTrue(isOriginAllowed('https://a.ru', $allowed));
        $this->assertTrue(isOriginAllowed('HTTPS://A.RU', $allowed));
    }

    public function testOriginWithOtherSchemeSubdomainOrPortIsDenied(): void
    {
        $allowed = ['https://a.ru'];

        $this->assertFalse(isOriginAllowed('http://a.ru', $allowed));
        $this->assertFalse(isOriginAllowed('https://www.a.ru', $allowed));
        $this->assertFalse(isOriginAllowed('https://a.ru:8443', $allowed));
        $this->assertFalse(isOriginAllowed('https://evil-a.ru', $allowed));
    }

    public function testMissingOrNullOriginIsDenied(): void
    {
        $this->assertFalse(isOriginAllowed(null, ['https://a.ru']));
        $this->assertFalse(isOriginAllowed('null', ['https://a.ru']));
    }

    public function testEmptyAllowListDeniesEverything(): void
    {
        $this->assertFalse(isOriginAllowed('https://a.ru', []));
    }

    // ─── corsHeadersForOrigin() ─────────────────────────────────────────────

    public function testCorsReflectsExactOriginNotWildcard(): void
    {
        $headers = corsHeadersForOrigin('https://a.ru');

        $this->assertContains('Access-Control-Allow-Origin: https://a.ru', $headers);
        $this->assertContains('Vary: Origin', $headers);
        $this->assertNotContains('Access-Control-Allow-Origin: *', $headers);
    }

    // ─── conversation id ────────────────────────────────────────────────────

    public function testGeneratedConversationIdIsValid(): void
    {
        $this->assertTrue(isValidConversationId(generateConversationId()));
    }

    public function testConversationIdRejectsWrongFormat(): void
    {
        $this->assertFalse(isValidConversationId(''));
        $this->assertFalse(isValidConversationId(str_repeat('a', 31)));
        $this->assertFalse(isValidConversationId(str_repeat('a', 33)));
        $this->assertFalse(isValidConversationId(str_repeat('g', 32)));
        $this->assertFalse(isValidConversationId("'; DROP TABLE x; --"));
        $this->assertFalse(isValidConversationId(str_repeat('a', 32) . "\n"));
    }

    // ─── absoluteUrl() ──────────────────────────────────────────────────────

    public function testAbsoluteUrlJoinsWithSingleSlash(): void
    {
        $this->assertSame('https://shop.ru/product/sofa', absoluteUrl('https://shop.ru', '/product/sofa'));
        $this->assertSame('https://shop.ru/product/sofa', absoluteUrl('https://shop.ru/', '/product/sofa'));
        $this->assertSame('https://shop.ru/product/sofa', absoluteUrl('https://shop.ru', 'product/sofa'));
    }

    // ─── validateApiClientInput() ───────────────────────────────────────────

    public function testValidClientInputHasNoErrors(): void
    {
        $errors = validateApiClientInput(['name' => 'Лендинг', 'allowed_origins' => 'https://a.ru']);

        $this->assertSame(['name' => false, 'origins' => false], $errors);
    }

    public function testEmptyNameIsError(): void
    {
        $errors = validateApiClientInput(['name' => '  ', 'allowed_origins' => 'https://a.ru']);

        $this->assertTrue($errors['name']);
    }

    public function testTooLongNameIsError(): void
    {
        $errors = validateApiClientInput(['name' => str_repeat('а', API_CLIENT_NAME_MAX + 1), 'allowed_origins' => 'https://a.ru']);

        $this->assertTrue($errors['name']);
    }

    public function testOnlyInvalidOriginsIsError(): void
    {
        $errors = validateApiClientInput(['name' => 'Лендинг', 'allowed_origins' => "мусор\nexample.com"]);

        $this->assertTrue($errors['origins']);
    }
}
