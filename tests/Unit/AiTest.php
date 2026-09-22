<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AiTest extends TestCase
{
    // ─── aiClassForAssistant() ─────────────────────────────────────────────

    public function testSpecsAssistantIsAnonymousClass(): void
    {
        $this->assertSame(\AI_CLASS_ANONYMOUS, aiClassForAssistant('specs'));
    }

    public function testDescriptionAssistantIsAnonymousClass(): void
    {
        $this->assertSame(\AI_CLASS_ANONYMOUS, aiClassForAssistant('description'));
    }

    public function testConsultantAssistantIsUserInputClass(): void
    {
        $this->assertSame(\AI_CLASS_USER_INPUT, aiClassForAssistant('consultant'));
    }

    public function testPickerAssistantIsUserInputClass(): void
    {
        $this->assertSame(\AI_CLASS_USER_INPUT, aiClassForAssistant('picker'));
    }

    public function testUnknownAssistantReturnsNull(): void
    {
        $this->assertNull(aiClassForAssistant('unknown'));
    }

    // ─── decodeAiJson() ──────────────────────────────────────────────────

    public function testDecodesPlainJson(): void
    {
        $this->assertSame(['a' => 1], decodeAiJson('{"a": 1}'));
    }

    public function testDecodesJsonWrappedInMarkdownJsonFence(): void
    {
        $text = "```json\n{\"a\": 1}\n```";
        $this->assertSame(['a' => 1], decodeAiJson($text));
    }

    public function testDecodesJsonWrappedInPlainFence(): void
    {
        $text = "```\n{\"a\": 1}\n```";
        $this->assertSame(['a' => 1], decodeAiJson($text));
    }

    public function testDecodesJsonWithSurroundingWhitespace(): void
    {
        $this->assertSame(['a' => 1], decodeAiJson("  \n{\"a\": 1}\n  "));
    }

    public function testPlainTextWithoutJsonReturnsNull(): void
    {
        $this->assertNull(decodeAiJson('извините, не могу ответить'));
    }

    public function testEmptyStringReturnsNull(): void
    {
        $this->assertNull(decodeAiJson(''));
    }

    public function testWhitespaceOnlyStringReturnsNull(): void
    {
        $this->assertNull(decodeAiJson("   \n  "));
    }

    public function testJsonScalarIsNotAnArrayReturnsNull(): void
    {
        $this->assertNull(decodeAiJson('"просто строка"'));
    }

    public function testJsonArrayOfValuesDecodesToArray(): void
    {
        $this->assertSame(['divan-milan', 'divan-roma'], decodeAiJson('["divan-milan", "divan-roma"]'));
    }

    // ─── costRubFromUsd() ────────────────────────────────────────────────

    public function testCostRubFromUsdMultipliesByRate(): void
    {
        $this->assertSame('95.50', costRubFromUsd(1.0, '95.50'));
    }

    public function testCostRubFromUsdRoundsHalfUp(): void
    {
        // 0.001234 * 95.50 = 0.117847 → округление до копеек вверх
        $this->assertSame('0.12', costRubFromUsd(0.001234, '95.50'));
    }

    public function testCostRubFromUsdZeroCost(): void
    {
        $this->assertSame('0.00', costRubFromUsd(0.0, '95.50'));
    }

    public function testCostRubFromUsdZeroRateGivesZero(): void
    {
        $this->assertSame('0.00', costRubFromUsd(0.05, '0'));
    }

    // ─── costRubFromTokens() ─────────────────────────────────────────────

    public function testCostRubFromTokensCalculatesPerThousand(): void
    {
        // 2000 токенов * 1.50 руб / 1000 = 3.00
        $this->assertSame('3.00', costRubFromTokens(1000, 1000, '1.50'));
    }

    public function testCostRubFromTokensZeroTokensGivesZero(): void
    {
        $this->assertSame('0.00', costRubFromTokens(0, 0, '1.50'));
    }

    public function testCostRubFromTokensSumsInputAndOutput(): void
    {
        // (200 + 133) токена * 1.00 / 1000 = 0.333 → 0.33
        $this->assertSame('0.33', costRubFromTokens(200, 133, '1.00'));
    }
}
