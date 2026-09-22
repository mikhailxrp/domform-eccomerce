<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Ai.php';

/**
 * Разбор характеристик Товара (`FR-AI-001`, `ADR-049`) — чистые
 * функции: сборка промпта и разбор ответа модели. Никакой БД —
 * известные значения (`$knownValues`) читает `Models/AiSpec.php` и
 * передаёт сюда уже готовым массивом.
 */

/**
 * Куда падает подтверждённое предложение (`database.md`,
 * `ai_spec_suggestions.target`): `variant_material`/`variant_mechanism`
 * — колонки Варианта, `color` — только подсказка (цвет остаётся
 * атрибутом фото, `ADR-006`), `spec` — свободная характеристика
 * (размер, форма и т.п.) в `product_specs`.
 */
const AI_SPEC_TARGETS = ['spec', 'variant_material', 'variant_mechanism', 'color'];

/**
 * Человекочитаемое название для целей с фиксированным смыслом — модель
 * не выбирает название сама, чтобы предложения одного target всегда
 * подписывались одинаково в экране ревью (Таск 3). Для `spec` название
 * — то, что вернула модель (произвольная характеристика).
 */
const AI_SPEC_TARGET_LABELS = [
    'variant_material'  => 'Материал',
    'variant_mechanism' => 'Механизм раскладки',
    'color'             => 'Цвет',
];

const AI_SPEC_VALUE_MAX_LENGTH = 255;
const AI_SPEC_NAME_MAX_LENGTH  = 100;

/**
 * Максимум предложений с одного разбора — защита от аномально большого
 * или зацикленного ответа модели, не ограничение самой характеристики
 * Товара (`FR-CARD-005` не задаёт числового предела).
 */
const AI_SPEC_SUGGESTIONS_MAX = 20;

/**
 * `$product` — `name`, `description` (`Models/Product.php::findProductForAdmin()`
 * отдаёт оба поля); `$knownValues` — `getKnownSpecValues()`, добавляется
 * в промпт только для единообразия названий (материал «рогожка», а не
 * «Рогожка»/«ткань рогожка»), не как жёсткое ограничение — модель
 * всё равно может предложить новое значение, а `normalizeSpecSuggestions()`
 * пометит его `needs_decision`, если оно не входит в список.
 */
function buildSpecsPrompt(array $product, array $knownValues): array
{
    $system = <<<PROMPT
        Ты — помощник интернет-магазина мебели «ДомФорм». По текстовому
        описанию Товара определи значения характеристик для карточки.

        Ответь ТОЛЬКО чистым JSON-массивом объектов, без пояснений и без
        обрамления в ```. Формат каждого объекта:
        {"target": "variant_material" | "variant_mechanism" | "color" | "spec", "name": "...", "value": "..."}

        Правила:
        - variant_material — материал обивки/корпуса, если явно назван в тексте (одно значение); "name" не нужен.
        - variant_mechanism — механизм раскладки, только если явно назван (не у всех категорий он есть); "name" не нужен.
        - color — цвет, только если явно назван; "name" не нужен.
        - spec — любая другая характеристика из текста (размер, форма, глубина, ширина и т.п.); "name" — короткое название характеристики ровно как её стоит показать покупателю ("Ширина", "Форма").
        - Характеристика, не упомянутая в тексте явно, вообще не включается в ответ — не подставляй значение по умолчанию и не выдумывай.
        - Не описывай то, чего нет в тексте описания.
        PROMPT;

    $knownHint = buildKnownValuesHint($knownValues);
    if ($knownHint !== '') {
        $system .= "\n\nУже встречались в каталоге (для единообразия названий, не обязательны): {$knownHint}";
    }

    $user = 'Название: ' . $product['name'] . "\nОписание: " . ($product['description'] ?? '');

    return [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ];
}

function buildKnownValuesHint(array $knownValues): string
{
    $parts = [];

    foreach (['material' => 'материал', 'mechanism' => 'механизм', 'color' => 'цвет'] as $key => $label) {
        $values = array_slice($knownValues[$key] ?? [], 0, 15);
        if ($values !== []) {
            $parts[] = "{$label} — " . implode(', ', $values);
        }
    }

    return implode('; ', $parts);
}

/**
 * `$decoded` — результат `decodeAiJson()` на тексте ответа модели;
 * `null` (разбор не удался) обрабатывается как пустой список, не
 * ошибка — вызывающий код (`AdminAiSpecController::run()`) сам считает
 * это отказом по конкретному Товару.
 *
 * Отбрасывает: элементы без `target` из `AI_SPEC_TARGETS`, с пустым
 * `value`, `spec` без `name` (правило 2 `FR-AI-001` — ничего не
 * подставляется по умолчанию, лучше пропустить, чем выдумать). Схлопывает
 * дубли одной цели/названия — оставляет последнее предложение модели.
 * `needs_decision` — только для целей с закрытым словарём
 * (`variant_material`/`variant_mechanism`/`color`), когда значение не
 * входит в `$knownValues` (правило 3): у `spec` (размер, форма и
 * подобное) закрытого словаря по Категории нет — значения нечисловые
 * дискретные, сравнивать не с чем, поэтому `spec` всегда `ok`
 * (`ADR-049`).
 */
function normalizeSpecSuggestions(?array $decoded, array $knownValues): array
{
    if ($decoded === null || $decoded === []) {
        return [];
    }

    $byKey = [];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $target = is_string($item['target'] ?? null) ? $item['target'] : '';
        if (!in_array($target, AI_SPEC_TARGETS, true)) {
            continue;
        }

        $value = is_string($item['value'] ?? null) ? trim($item['value']) : '';
        if ($value === '') {
            continue;
        }
        $value = mb_substr($value, 0, AI_SPEC_VALUE_MAX_LENGTH);

        if ($target === 'spec') {
            $name = is_string($item['name'] ?? null) ? trim($item['name']) : '';
            if ($name === '') {
                continue;
            }
            $name   = mb_substr($name, 0, AI_SPEC_NAME_MAX_LENGTH);
            $status = 'ok';
        } else {
            $name   = AI_SPEC_TARGET_LABELS[$target];
            $status = valueInKnownList($value, $knownValues[knownValuesKeyForTarget($target)] ?? [])
                ? 'ok'
                : 'needs_decision';
        }

        // Дубль той же цели/названия — оставляем последнее предложение
        // модели, не оба (`нормализуем`, не суммируем варианты).
        $byKey[$target . '|' . mb_strtolower($name)] = [
            'target' => $target,
            'name'   => $name,
            'value'  => $value,
            'status' => $status,
        ];

        if (count($byKey) >= AI_SPEC_SUGGESTIONS_MAX) {
            break;
        }
    }

    return array_values($byKey);
}

function knownValuesKeyForTarget(string $target): string
{
    return match ($target) {
        'variant_material'  => 'material',
        'variant_mechanism' => 'mechanism',
        'color'             => 'color',
        default             => '',
    };
}

/**
 * Сравнение без учёта регистра/краевых пробелов — модель нередко меняет
 * регистр («Рогожка» вместо «рогожка»), это не повод считать значение
 * новым.
 */
function valueInKnownList(string $value, array $knownList): bool
{
    $normalized = mb_strtolower(trim($value));

    foreach ($knownList as $known) {
        if (mb_strtolower(trim((string) $known)) === $normalized) {
            return true;
        }
    }

    return false;
}

/**
 * Ревью предложений (`FR-AI-001` правила 3–5, Таск 3 Фазы 9) — чистая
 * функция, БД не трогает. `$suggestions` — строки `ai_spec_suggestions`
 * Товара (`id`, `target`, `name`, `value`, `status`);
 * `$validVariantIds` — id Вариантов **этого же** Товара
 * (`getAllProductVariants()`), подмена чужого `variant_id` отклоняется
 * здесь же, до записи в БД; `$input` — сырой POST:
 * `accepted[id]`, `value[id]`, `variant_id[id]`.
 *
 * `color` никогда не попадает в `$accepted` — только подсказка
 * («Решения фазы» Таска 1: цвет остаётся атрибутом фото Варианта,
 * `ADR-006`). Непомеченный чекбоксом пункт — молча отклонён, не
 * ошибка. `needs_decision` требует, чтобы отправленное значение
 * отличалось от предложенного моделью — иначе продолжает считаться
 * неразобранным (правило 3: подтверждение не может быть слепым для
 * значения, помеченного как «требует решения»).
 */
function validateSpecReviewInput(array $suggestions, array $validVariantIds, array $input): array
{
    $acceptedFlags = (array) ($input['accepted'] ?? []);
    $values        = (array) ($input['value'] ?? []);
    $variantIds    = (array) ($input['variant_id'] ?? []);

    $accepted = [];
    $errors   = [];

    foreach ($suggestions as $suggestion) {
        $id = (int) $suggestion['id'];

        if ($suggestion['target'] === 'color') {
            continue;
        }

        if (empty($acceptedFlags[$id])) {
            continue;
        }

        $value = is_string($values[$id] ?? null) ? trim($values[$id]) : '';
        if ($value === '') {
            $errors[$id] = 'Значение не может быть пустым.';
            continue;
        }
        $value = mb_substr($value, 0, AI_SPEC_VALUE_MAX_LENGTH);

        if ($suggestion['target'] === 'spec') {
            $accepted[] = ['target' => 'spec', 'name' => $suggestion['name'], 'value' => $value];
            continue;
        }

        $variantId = (int) ($variantIds[$id] ?? 0);
        if (!in_array($variantId, $validVariantIds, true)) {
            $errors[$id] = 'Выберите Вариант из списка.';
            continue;
        }

        $isEdited = mb_strtolower($value) !== mb_strtolower(trim((string) $suggestion['value']));
        if ($suggestion['status'] === 'needs_decision' && !$isEdited) {
            $errors[$id] = 'Значение требует решения — поправьте его перед принятием.';
            continue;
        }

        $accepted[] = ['target' => $suggestion['target'], 'value' => $value, 'variant_id' => $variantId];
    }

    return ['accepted' => $accepted, 'errors' => $errors];
}
