<?php

declare(strict_types=1);

/**
 * Генератор черновика описания Товара (`FR-AI-002`, Таск 4 Фазы 9) —
 * чистые функции: разбор кратких данных, сборка промпта модели,
 * нормализация ответа. Никакой БД — сохранение черновика делает
 * `Models/Product.php::saveDescriptionDraft()`.
 */

const AI_DESCRIPTION_FIELD_MAX_LENGTH = 200;
const AI_DESCRIPTION_DRAFT_MAX_LENGTH = 2000;

/**
 * Единственный источник кратких данных, которые вообще могут попасть в
 * промпт (правило 3 `FR-AI-002` — ничего сверх того, что ввёл
 * Администратор в этом блоке: ни `name`, ни `description` Товара).
 */
const AI_DESCRIPTION_FIELD_LABELS = [
    'category'  => 'Категория',
    'material'  => 'Материал',
    'size'      => 'Размер',
    'mechanism' => 'Механизм',
];

/**
 * `$input` — сырой POST (`category`/`material`/`size`/`mechanism`):
 * `trim`, лимит длины, пустые поля отбрасываются (не подставляются
 * пустой строкой в промпт). Все поля пустые → `error`, промпт строить
 * не из чего.
 *
 * @return array{brief: array<string, string>, error: string|null}
 */
function validateDescriptionBrief(array $input): array
{
    $brief = [];

    foreach (array_keys(AI_DESCRIPTION_FIELD_LABELS) as $field) {
        $value = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
        if ($value === '') {
            continue;
        }
        $brief[$field] = mb_substr($value, 0, AI_DESCRIPTION_FIELD_MAX_LENGTH);
    }

    if ($brief === []) {
        return ['brief' => [], 'error' => 'Заполните хотя бы одно поле.'];
    }

    return ['brief' => $brief, 'error' => null];
}

/**
 * `$brief` — результат `validateDescriptionBrief()['brief']`, уже без
 * пустых полей.
 *
 * @param array<string, string> $brief
 * @return array<int, array{role: string, content: string}>
 */
function buildDescriptionPrompt(array $brief): array
{
    $system = <<<PROMPT
        Ты — копирайтер интернет-магазина мебели «ДомФорм». По кратким
        данным о Товаре напиши черновик описания для карточки товара.

        Ответь ТОЛЬКО текстом описания на русском языке, без заголовка,
        без markdown-разметки и без обрамления в ```.

        Правила:
        - Пиши только по данным, которые тебе передали — не придумывай
          материал, размер, механизм или другие характеристики, которых
          нет во входных данных.
        - 2-4 предложения, разговорный, но деловой стиль, без канцелярита.
        - Не упоминай цену, скидки, доставку и сроки изготовления.
        PROMPT;

    $lines = [];
    foreach (AI_DESCRIPTION_FIELD_LABELS as $field => $label) {
        if (isset($brief[$field])) {
            $lines[] = $label . ': ' . $brief[$field];
        }
    }

    return [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => implode("\n", $lines)],
    ];
}

/**
 * Модель нередко всё равно оборачивает ответ в ```` ``` ```` или
 * добавляет обрамляющие кавычки — снимаем перед показом. Обрезка длины
 * — защита от аномально длинного ответа, не литературное ограничение
 * (`FR-AI-002` числового предела не задаёт).
 */
function normalizeDraft(string $text): string
{
    $draft = trim($text);
    if ($draft === '') {
        return '';
    }

    if (str_starts_with($draft, '```')) {
        $draft = (string) preg_replace('/^```[a-zA-Z]*\s*/', '', $draft);
        $draft = (string) preg_replace('/```\s*$/', '', $draft);
        $draft = trim($draft);
    }

    if (mb_strlen($draft) >= 2 && mb_substr($draft, 0, 1) === '"' && mb_substr($draft, -1) === '"') {
        $draft = trim(mb_substr($draft, 1, -1));
    }

    return mb_substr($draft, 0, AI_DESCRIPTION_DRAFT_MAX_LENGTH);
}
