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

    // ─── validateAiSettingsInput() ───────────────────────────────────────

    private function validAiSettingsInput(): array
    {
        return [
            'ai_monthly_limit_rub'   => '5000',
            'ai_usd_rate'            => '95.00',
            'ai_yandex_price_per_1k' => '1.20',
        ];
    }

    public function testValidAiSettingsInputHasNoErrors(): void
    {
        $errors = validateAiSettingsInput($this->validAiSettingsInput());
        $this->assertNotContains(true, $errors);
    }

    public function testAiSettingsInputRejectsZeroLimit(): void
    {
        $errors = validateAiSettingsInput(array_merge($this->validAiSettingsInput(), ['ai_monthly_limit_rub' => '0']));
        $this->assertTrue($errors['ai_monthly_limit_rub']);
    }

    public function testAiSettingsInputRejectsLimitAboveMax(): void
    {
        $errors = validateAiSettingsInput(array_merge($this->validAiSettingsInput(), ['ai_monthly_limit_rub' => '1000001']));
        $this->assertTrue($errors['ai_monthly_limit_rub']);
    }

    public function testAiSettingsInputRejectsNonNumericLimit(): void
    {
        $errors = validateAiSettingsInput(array_merge($this->validAiSettingsInput(), ['ai_monthly_limit_rub' => 'много']));
        $this->assertTrue($errors['ai_monthly_limit_rub']);
    }

    public function testAiSettingsInputRejectsZeroUsdRate(): void
    {
        $errors = validateAiSettingsInput(array_merge($this->validAiSettingsInput(), ['ai_usd_rate' => '0']));
        $this->assertTrue($errors['ai_usd_rate']);
    }

    public function testAiSettingsInputAcceptsFourDecimalUsdRate(): void
    {
        $errors = validateAiSettingsInput(array_merge($this->validAiSettingsInput(), ['ai_usd_rate' => '95.1234']));
        $this->assertFalse($errors['ai_usd_rate']);
    }

    public function testAiSettingsInputRejectsNegativeYandexPrice(): void
    {
        $errors = validateAiSettingsInput(array_merge($this->validAiSettingsInput(), ['ai_yandex_price_per_1k' => '-1']));
        $this->assertTrue($errors['ai_yandex_price_per_1k']);
    }

    // ─── isAiLimitExceeded() ─────────────────────────────────────────────

    public function testLimitNotExceededWhenSpendBelowLimit(): void
    {
        $this->assertFalse(isAiLimitExceeded('4999.99', '5000'));
    }

    public function testLimitExceededWhenSpendEqualsLimit(): void
    {
        $this->assertTrue(isAiLimitExceeded('5000.00', '5000'));
    }

    public function testLimitExceededWhenSpendAboveLimit(): void
    {
        $this->assertTrue(isAiLimitExceeded('5000.01', '5000'));
    }

    // ─── summarizeAiRequestStats() ───────────────────────────────────────

    public function testSummarizeAiRequestStatsTotalsSpendAndCounts(): void
    {
        $rows = [
            ['assistant' => 'consultant', 'task_class' => 'user_input', 'provider' => 'yandexgpt', 'requests_count' => 10, 'errors_count' => 1, 'spend' => '12.50'],
            ['assistant' => 'specs', 'task_class' => 'anonymous', 'provider' => 'openrouter', 'requests_count' => 5, 'errors_count' => 0, 'spend' => '7.50'],
        ];

        $summary = summarizeAiRequestStats($rows);

        $this->assertSame('20.00', $summary['total_spend']);
        $this->assertSame(15, $summary['total_requests']);
        $this->assertSame(1, $summary['total_errors']);
        $this->assertSame('12.50', $summary['by_assistant']['consultant']['spend']);
        $this->assertSame('7.50', $summary['by_class']['anonymous']['spend']);
    }

    public function testSummarizeAiRequestStatsMergesSameAssistantAcrossProviders(): void
    {
        $rows = [
            ['assistant' => 'consultant', 'task_class' => 'user_input', 'provider' => 'yandexgpt', 'requests_count' => 3, 'errors_count' => 0, 'spend' => '5.00'],
            ['assistant' => 'consultant', 'task_class' => 'user_input', 'provider' => 'yandexgpt', 'requests_count' => 2, 'errors_count' => 1, 'spend' => '3.00'],
        ];

        $summary = summarizeAiRequestStats($rows);

        $this->assertSame('8.00', $summary['by_assistant']['consultant']['spend']);
        $this->assertSame(5, $summary['by_assistant']['consultant']['requests_count']);
        $this->assertSame(1, $summary['by_assistant']['consultant']['errors_count']);
    }

    public function testSummarizeAiRequestStatsEmptyRowsGivesZeroTotals(): void
    {
        $summary = summarizeAiRequestStats([]);

        $this->assertSame('0', $summary['total_spend']);
        $this->assertSame(0, $summary['total_requests']);
        $this->assertSame([], $summary['by_assistant']);
    }
}
