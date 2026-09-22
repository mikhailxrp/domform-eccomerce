<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AiDescriptionTest extends TestCase
{
    // ─── validateDescriptionBrief() ────────────────────────────────────────

    public function testAllFieldsEmptyReturnsError(): void
    {
        $result = validateDescriptionBrief(['category' => '', 'material' => '', 'size' => '', 'mechanism' => '']);

        $this->assertSame([], $result['brief']);
        $this->assertNotNull($result['error']);
    }

    public function testMissingKeysTreatedAsEmpty(): void
    {
        $result = validateDescriptionBrief([]);

        $this->assertSame([], $result['brief']);
        $this->assertNotNull($result['error']);
    }

    public function testOnlyNonEmptyFieldsAreKept(): void
    {
        $result = validateDescriptionBrief(['category' => 'Диваны', 'material' => '', 'size' => '', 'mechanism' => '']);

        $this->assertNull($result['error']);
        $this->assertSame(['category' => 'Диваны'], $result['brief']);
    }

    public function testFieldsAreTrimmed(): void
    {
        $result = validateDescriptionBrief(['category' => '  Диваны  ', 'material' => '', 'size' => '', 'mechanism' => '']);

        $this->assertSame('Диваны', $result['brief']['category']);
    }

    public function testWhitespaceOnlyFieldTreatedAsEmpty(): void
    {
        $result = validateDescriptionBrief(['category' => '   ', 'material' => 'Рогожка', 'size' => '', 'mechanism' => '']);

        $this->assertArrayNotHasKey('category', $result['brief']);
        $this->assertSame('Рогожка', $result['brief']['material']);
    }

    public function testFieldIsCutToMaxLength(): void
    {
        $result = validateDescriptionBrief(['category' => str_repeat('а', 500), 'material' => '', 'size' => '', 'mechanism' => '']);

        $this->assertSame(AI_DESCRIPTION_FIELD_MAX_LENGTH, mb_strlen($result['brief']['category']));
    }

    // ─── buildDescriptionPrompt() ──────────────────────────────────────────

    public function testPromptContainsOnlyProvidedFields(): void
    {
        $messages = buildDescriptionPrompt(['category' => 'Диваны', 'material' => 'Рогожка']);
        $userMessage = $messages[1]['content'];

        $this->assertStringContainsString('Категория: Диваны', $userMessage);
        $this->assertStringContainsString('Материал: Рогожка', $userMessage);
        $this->assertStringNotContainsString('Размер', $userMessage);
        $this->assertStringNotContainsString('Механизм', $userMessage);
    }

    // ─── normalizeDraft() ───────────────────────────────────────────────────

    public function testEmptyTextReturnsEmptyString(): void
    {
        $this->assertSame('', normalizeDraft(''));
        $this->assertSame('', normalizeDraft('   '));
    }

    public function testPlainTextIsTrimmed(): void
    {
        $this->assertSame('Удобный диван для гостиной.', normalizeDraft('  Удобный диван для гостиной.  '));
    }

    public function testMarkdownCodeFenceIsStripped(): void
    {
        $this->assertSame('Удобный диван.', normalizeDraft("```\nУдобный диван.\n```"));
    }

    public function testWrappingQuotesAreStripped(): void
    {
        $this->assertSame('Удобный диван.', normalizeDraft('"Удобный диван."'));
    }

    public function testDraftIsCutToMaxLength(): void
    {
        $result = normalizeDraft(str_repeat('а', 3000));

        $this->assertSame(AI_DESCRIPTION_DRAFT_MAX_LENGTH, mb_strlen($result));
    }
}
