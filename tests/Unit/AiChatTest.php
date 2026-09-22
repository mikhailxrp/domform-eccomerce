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

    public function testPromptContainsPageBodiesContactsAndCatalog(): void
    {
        $prompt = buildConsultantPrompt(
            [
                'delivery-payment' => 'Доставка по Краснодару за 3 дня.',
                'return-warranty'  => 'Гарантия 18 месяцев.',
                'showroom'         => 'Шоурум на ул. Ленина.',
            ],
            [
                'phone'      => '+7 900 000-00-00',
                'whatsapp'   => 'https://wa.me/79000000000',
                'address'    => 'ул. Ленина, 1',
                'work_hours' => 'пн-пт 9-18',
            ],
            [['slug' => 'divan-uglovoy', 'name' => 'Диван угловой', 'category_name' => 'Диваны', 'min_price' => '50000']]
        );

        $this->assertStringContainsString('Доставка по Краснодару за 3 дня.', $prompt);
        $this->assertStringContainsString('Гарантия 18 месяцев.', $prompt);
        $this->assertStringContainsString('Шоурум на ул. Ленина.', $prompt);
        $this->assertStringContainsString('+7 900 000-00-00', $prompt);
        $this->assertStringContainsString('https://wa.me/79000000000', $prompt);
        $this->assertStringContainsString('ул. Ленина, 1', $prompt);
        $this->assertStringContainsString('divan-uglovoy', $prompt);
        $this->assertStringContainsString('Диван угловой', $prompt);
    }

    // ─── buildCatalogSnapshotText() ──────────────────────────────────────────

    public function testCatalogSnapshotFormatsEachProduct(): void
    {
        $text = buildCatalogSnapshotText([
            ['slug' => 'kuhnya-loft', 'name' => 'Кухня Лофт', 'category_name' => 'Кухни', 'min_price' => '120000'],
        ]);

        $this->assertStringContainsString('kuhnya-loft', $text);
        $this->assertStringContainsString('Кухня Лофт', $text);
        $this->assertStringContainsString('Кухни', $text);
    }

    public function testEmptyCatalogSnapshotDoesNotCrash(): void
    {
        $this->assertSame('Каталог временно пуст.', buildCatalogSnapshotText([]));
    }

    // ─── decodeConsultantReply() ─────────────────────────────────────────────

    public function testDecodesValidReplyWithProductSlug(): void
    {
        $result = decodeConsultantReply(['reply' => 'Вот подходящий диван.', 'product_slug' => 'divan-uglovoy'], 'raw');

        $this->assertSame('Вот подходящий диван.', $result['reply']);
        $this->assertSame('divan-uglovoy', $result['product_slug']);
    }

    public function testDecodesReplyWithNullProductSlug(): void
    {
        $result = decodeConsultantReply(['reply' => 'Не нашли подходящего.', 'product_slug' => null], 'raw');

        $this->assertNull($result['product_slug']);
    }

    public function testDecodesReplyWithStringNullProductSlug(): void
    {
        $result = decodeConsultantReply(['reply' => 'Не нашли подходящего.', 'product_slug' => 'null'], 'raw');

        $this->assertNull($result['product_slug']);
    }

    public function testFallsBackToRawTextWhenDecodedIsNull(): void
    {
        $result = decodeConsultantReply(null, 'Просто текстовый ответ модели.');

        $this->assertSame('Просто текстовый ответ модели.', $result['reply']);
        $this->assertNull($result['product_slug']);
    }

    public function testFallsBackToRawTextWhenReplyKeyMissing(): void
    {
        $result = decodeConsultantReply(['product_slug' => 'divan-uglovoy'], 'Текст без JSON.');

        $this->assertSame('Текст без JSON.', $result['reply']);
        $this->assertNull($result['product_slug']);
    }

    // ─── extractOrderLookupQuery() ───────────────────────────────────────────

    public function testExtractsOrderIdAndPhone(): void
    {
        $result = extractOrderLookupQuery('Мой заказ 105, телефон +7 900 123-45-67');

        $this->assertNotNull($result);
        $this->assertSame(105, $result['order_id']);
        $this->assertSame('+79001234567', $result['phone']);
        $this->assertNull($result['email']);
    }

    public function testExtractsOrderIdAndEmail(): void
    {
        $result = extractOrderLookupQuery('Заказ №42, email ivan@example.com');

        $this->assertNotNull($result);
        $this->assertSame(42, $result['order_id']);
        $this->assertSame('ivan@example.com', $result['email']);
    }

    public function testReturnsNullWithoutOrderWord(): void
    {
        $this->assertNull(extractOrderLookupQuery('Мой номер 105, телефон +7 900 123-45-67'));
    }

    public function testReturnsNullWithoutContact(): void
    {
        $this->assertNull(extractOrderLookupQuery('Что с заказом 105?'));
    }

    public function testReturnsNullWithoutOrderNumber(): void
    {
        $this->assertNull(extractOrderLookupQuery('Хочу узнать про свой заказ, телефон +7 900 123-45-67'));
    }

    // ─── buildOrderLookupReply() ─────────────────────────────────────────────

    public function testOrderLookupReplyContainsKeyFacts(): void
    {
        $reply = buildOrderLookupReply([
            'id'                 => 105,
            'status'             => 'in_production',
            'total'              => '85000',
            'created_at'         => '2026-09-01 10:00:00',
            'fulfillment_method' => 'delivery',
        ]);

        $this->assertStringContainsString('105', $reply);
        $this->assertStringContainsString('В производстве', $reply);
        $this->assertStringContainsString('01.09.2026', $reply);
        $this->assertStringContainsString('Доставка', $reply);
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
