<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AiChatTest extends TestCase
{
    // ─── normalizeChatQuestion() ────────────────────────────────────────────

    public function testQuestionIsTrimmed(): void
    {
        $this->assertSame('Когда доставка?', normalizeChatQuestion('  Когда доставка?  '));
    }

    public function testQuestionIsCutToMaxLength(): void
    {
        $result = normalizeChatQuestion(str_repeat('а', 1000));

        $this->assertSame(AI_MAX_QUESTION_LENGTH, mb_strlen($result));
    }

    public function testEmptyQuestionStaysEmpty(): void
    {
        $this->assertSame('', normalizeChatQuestion('   '));
    }

    // ─── trimChatHistory() ───────────────────────────────────────────────────

    public function testShortHistoryIsUnchanged(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'Вопрос 1'],
            ['role' => 'assistant', 'content' => 'Ответ 1'],
        ];

        $this->assertSame($history, trimChatHistory($history));
    }

    public function testLongHistoryKeepsOnlyLastMessages(): void
    {
        $history = [];
        for ($i = 1; $i <= AI_CHAT_HISTORY_LIMIT + 4; $i++) {
            $history[] = ['role' => 'user', 'content' => "Сообщение {$i}"];
        }

        $result = trimChatHistory($history);

        $this->assertCount(AI_CHAT_HISTORY_LIMIT, $result);
        $this->assertSame('Сообщение ' . (AI_CHAT_HISTORY_LIMIT + 4), $result[array_key_last($result)]['content']);
    }

    // ─── buildConsultantPrompt() ─────────────────────────────────────────────

    public function testPromptContainsPageBodiesAndContacts(): void
    {
        $prompt = buildConsultantPrompt(
            ['delivery-payment' => 'Доставка по Краснодару за 3 дня.', 'return-warranty' => 'Гарантия 18 месяцев.'],
            ['phone' => '+7 900 000-00-00', 'whatsapp' => 'https://wa.me/79000000000']
        );

        $this->assertStringContainsString('Доставка по Краснодару за 3 дня.', $prompt);
        $this->assertStringContainsString('Гарантия 18 месяцев.', $prompt);
        $this->assertStringContainsString('+7 900 000-00-00', $prompt);
        $this->assertStringContainsString('https://wa.me/79000000000', $prompt);
    }

    // ─── chatFallbackPayload() ───────────────────────────────────────────────

    public function testFallbackPayloadShape(): void
    {
        $payload = chatFallbackPayload(['phone' => '+7 900 000-00-00', 'whatsapp' => 'https://wa.me/79000000000']);

        $this->assertSame([
            'unavailable' => true,
            'phone'       => '+7 900 000-00-00',
            'whatsapp'    => 'https://wa.me/79000000000',
        ], $payload);
    }

    public function testFallbackPayloadHandlesMissingContacts(): void
    {
        $payload = chatFallbackPayload([]);

        $this->assertSame(['unavailable' => true, 'phone' => '', 'whatsapp' => ''], $payload);
    }

    // ─── chatLimitReached() ──────────────────────────────────────────────────

    public function testLimitDisabledNeverBlocks(): void
    {
        $this->assertFalse(chatLimitReached(AI_CHAT_DEMO_LIMIT + 10, false));
    }

    public function testLimitEnabledAllowsUpToLimit(): void
    {
        $this->assertFalse(chatLimitReached(AI_CHAT_DEMO_LIMIT - 1, true));
    }

    public function testLimitEnabledBlocksAtLimit(): void
    {
        $this->assertTrue(chatLimitReached(AI_CHAT_DEMO_LIMIT, true));
    }

    public function testLimitEnabledBlocksAboveLimit(): void
    {
        $this->assertTrue(chatLimitReached(AI_CHAT_DEMO_LIMIT + 5, true));
    }

    // ─── chatLimitReachedPayload() ───────────────────────────────────────────

    public function testLimitReachedPayloadShape(): void
    {
        $payload = chatLimitReachedPayload();

        $this->assertTrue($payload['limit_reached']);
        $this->assertStringContainsString((string) AI_CHAT_DEMO_LIMIT, $payload['message']);
    }

    // ─── buildDemoGreeting() ─────────────────────────────────────────────────

    public function testGreetingContainsModelNameAndLimit(): void
    {
        $greeting = buildDemoGreeting('yandexgpt/latest');

        $this->assertStringContainsString('yandexgpt/latest', $greeting);
        $this->assertStringContainsString((string) AI_CHAT_DEMO_LIMIT, $greeting);
    }
}
