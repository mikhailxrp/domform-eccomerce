<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AiSpecsTest extends TestCase
{
    private const KNOWN = [
        'material'  => ['рогожка', 'экокожа'],
        'mechanism' => ['еврокнижка', 'дельфин'],
        'color'     => ['белый', 'графит'],
    ];

    // ─── normalizeSpecSuggestions() — базовые случаи ──────────────────────

    public function testNullDecodedReturnsEmptyArray(): void
    {
        $this->assertSame([], normalizeSpecSuggestions(null, self::KNOWN));
    }

    public function testEmptyDecodedReturnsEmptyArray(): void
    {
        $this->assertSame([], normalizeSpecSuggestions([], self::KNOWN));
    }

    public function testNonArrayItemIsSkipped(): void
    {
        $decoded = ['просто строка', ['target' => 'spec', 'name' => 'Ширина', 'value' => '90 см']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertCount(1, $result);
        $this->assertSame('Ширина', $result[0]['name']);
    }

    public function testUnknownTargetIsDropped(): void
    {
        $decoded = [['target' => 'price', 'value' => '100']];
        $this->assertSame([], normalizeSpecSuggestions($decoded, self::KNOWN));
    }

    public function testMissingTargetIsDropped(): void
    {
        $decoded = [['name' => 'Ширина', 'value' => '90 см']];
        $this->assertSame([], normalizeSpecSuggestions($decoded, self::KNOWN));
    }

    public function testEmptyValueIsDropped(): void
    {
        $decoded = [['target' => 'variant_material', 'value' => '']];
        $this->assertSame([], normalizeSpecSuggestions($decoded, self::KNOWN));
    }

    public function testWhitespaceOnlyValueIsDropped(): void
    {
        $decoded = [['target' => 'color', 'value' => '   ']];
        $this->assertSame([], normalizeSpecSuggestions($decoded, self::KNOWN));
    }

    public function testSpecWithoutNameIsDropped(): void
    {
        $decoded = [['target' => 'spec', 'value' => '90 см']];
        $this->assertSame([], normalizeSpecSuggestions($decoded, self::KNOWN));
    }

    // ─── target=spec — правило 2 FR-AI-001, всегда 'ok' ───────────────────

    public function testSpecSuggestionIsAlwaysOk(): void
    {
        $decoded = [['target' => 'spec', 'name' => 'Ширина', 'value' => '190 см']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame([
            ['target' => 'spec', 'name' => 'Ширина', 'value' => '190 см', 'status' => 'ok'],
        ], $result);
    }

    public function testSpecWithValueNotInAnyKnownListIsStillOk(): void
    {
        // У 'spec' нет закрытого словаря по Категории (размер/форма и
        // подобное не перечислимы) — 'needs_decision' здесь не применяется.
        $decoded = [['target' => 'spec', 'name' => 'Глубина', 'value' => '250 см']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame('ok', $result[0]['status']);
    }

    // ─── target=variant_material/variant_mechanism/color — словарь ────────

    public function testMaterialInKnownListIsOk(): void
    {
        $decoded = [['target' => 'variant_material', 'value' => 'рогожка']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame('ok', $result[0]['status']);
        $this->assertSame('Материал', $result[0]['name']);
    }

    public function testMaterialNotInKnownListNeedsDecision(): void
    {
        $decoded = [['target' => 'variant_material', 'value' => 'вельвет']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame('needs_decision', $result[0]['status']);
    }

    public function testMaterialMatchIsCaseInsensitive(): void
    {
        $decoded = [['target' => 'variant_material', 'value' => 'РОГОЖКА']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame('ok', $result[0]['status']);
    }

    public function testMechanismNotInKnownListNeedsDecision(): void
    {
        $decoded = [['target' => 'variant_mechanism', 'value' => 'аккордеон']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame('needs_decision', $result[0]['status']);
        $this->assertSame('Механизм раскладки', $result[0]['name']);
    }

    public function testColorInKnownListIsOk(): void
    {
        $decoded = [['target' => 'color', 'value' => 'графит']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame('ok', $result[0]['status']);
        $this->assertSame('Цвет', $result[0]['name']);
    }

    public function testEmptyKnownListAlwaysNeedsDecision(): void
    {
        $decoded = [['target' => 'variant_material', 'value' => 'рогожка']];
        $result  = normalizeSpecSuggestions($decoded, ['material' => [], 'mechanism' => [], 'color' => []]);

        $this->assertSame('needs_decision', $result[0]['status']);
    }

    public function testModelSuppliedNameForFixedTargetIsIgnored(): void
    {
        // Модель может прислать своё "name" для variant_material — оно
        // не используется, название фиксировано (единообразие в ревью).
        $decoded = [['target' => 'variant_material', 'name' => 'Ткань', 'value' => 'рогожка']];
        $result  = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame('Материал', $result[0]['name']);
    }

    // ─── Дубли и лимиты ────────────────────────────────────────────────

    public function testDuplicateTargetAndNameKeepsLastOne(): void
    {
        $decoded = [
            ['target' => 'variant_material', 'value' => 'рогожка'],
            ['target' => 'variant_material', 'value' => 'экокожа'],
        ];
        $result = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertCount(1, $result);
        $this->assertSame('экокожа', $result[0]['value']);
    }

    public function testDuplicateSpecNameCaseInsensitiveKeepsLastOne(): void
    {
        $decoded = [
            ['target' => 'spec', 'name' => 'Ширина', 'value' => '90 см'],
            ['target' => 'spec', 'name' => 'ширина', 'value' => '120 см'],
        ];
        $result = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertCount(1, $result);
        $this->assertSame('120 см', $result[0]['value']);
    }

    public function testDifferentSpecNamesAreKeptSeparately(): void
    {
        $decoded = [
            ['target' => 'spec', 'name' => 'Ширина', 'value' => '90 см'],
            ['target' => 'spec', 'name' => 'Глубина', 'value' => '200 см'],
        ];
        $this->assertCount(2, normalizeSpecSuggestions($decoded, self::KNOWN));
    }

    public function testValueIsTruncatedToMaxLength(): void
    {
        $longValue = str_repeat('а', \AI_SPEC_VALUE_MAX_LENGTH + 50);
        $decoded   = [['target' => 'spec', 'name' => 'Комментарий', 'value' => $longValue]];
        $result    = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertSame(\AI_SPEC_VALUE_MAX_LENGTH, mb_strlen($result[0]['value']));
    }

    public function testSuggestionsAreCappedAtMax(): void
    {
        $decoded = [];
        for ($i = 0; $i < \AI_SPEC_SUGGESTIONS_MAX + 10; $i++) {
            $decoded[] = ['target' => 'spec', 'name' => "Характеристика {$i}", 'value' => 'значение'];
        }

        $result = normalizeSpecSuggestions($decoded, self::KNOWN);

        $this->assertCount(\AI_SPEC_SUGGESTIONS_MAX, $result);
    }

    // ─── buildKnownValuesHint() ────────────────────────────────────────

    public function testBuildKnownValuesHintListsAllThreeGroups(): void
    {
        $hint = buildKnownValuesHint(self::KNOWN);

        $this->assertStringContainsString('материал — рогожка, экокожа', $hint);
        $this->assertStringContainsString('механизм — еврокнижка, дельфин', $hint);
        $this->assertStringContainsString('цвет — белый, графит', $hint);
    }

    public function testBuildKnownValuesHintEmptyForNoData(): void
    {
        $this->assertSame('', buildKnownValuesHint(['material' => [], 'mechanism' => [], 'color' => []]));
    }

    // ─── buildSpecsPrompt() ────────────────────────────────────────────

    public function testBuildSpecsPromptReturnsSystemAndUserMessages(): void
    {
        $messages = buildSpecsPrompt(
            ['name' => 'Диван Милан', 'description' => 'Раскладной диван, механизм еврокнижка.'],
            self::KNOWN
        );

        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertStringContainsString('Диван Милан', $messages[1]['content']);
        $this->assertStringContainsString('еврокнижка', $messages[1]['content']);
    }
}
