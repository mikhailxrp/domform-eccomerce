<?php

declare(strict_types=1);

/**
 * Статические страницы (`content_pages`, `FR-CNT-001…006`, Таск 1
 * Фазы 8) — whitelist slug и рендер текста без обращения к БД.
 *
 * Тело страницы хранится плоским текстом с мини-разметкой, не HTML
 * (`ADR-044`): пустая строка разделяет блоки, строка `## ` —
 * подзаголовок, блок строк `- ` — список, всё остальное — абзац с
 * `<br>` между строками. Каждый текстовый фрагмент проходит через
 * `e()`, поэтому результат `renderContentBody()` — единственное место
 * во View, которое выводится без повторного экранирования.
 */

const CONTENT_PAGE_SLUGS = [
    'contacts',
    'about',
    'showroom',
    'delivery-payment',
    'return-warranty',
    'offer',
    'privacy-policy',
];

const CONTENT_HEADING_PREFIX = '## ';
const CONTENT_LIST_PREFIX    = '- ';

const CONTENT_TITLE_MAX_LENGTH = 200;

function isContentPageSlug(string $slug): bool
{
    return in_array($slug, CONTENT_PAGE_SLUGS, true);
}

/**
 * Форма редактирования `/admin/content/{slug}/edit` (Таск 6, `FR-ADM-003`)
 * — `$input`: `title`/`body` уже обрезаны `trim()` контроллером, как
 * везде в проекте (`normalizeReviewInput()` и т.п.).
 */
function validateContentPageInput(array $input): array
{
    $titleLength = mb_strlen((string) ($input['title'] ?? ''));
    $body        = (string) ($input['body'] ?? '');

    return [
        'title' => $titleLength < 1 || $titleLength > CONTENT_TITLE_MAX_LENGTH,
        'body'  => trim($body) === '',
    ];
}

/**
 * Ссылка «Открыть на сайте» в `/admin/content` (Таск 6) — три slug из
 * `CONTENT_PAGE_SLUGS` получили собственные маршруты (`about`/
 * `showroom` — Таск 3, `contacts` — Таск 4 Фазы 8), у остальных
 * четырёх адрес не изменился — общий `/pages/{slug}`.
 */
function publicUrlForContentSlug(string $slug): string
{
    return match ($slug) {
        'about', 'showroom', 'contacts' => '/' . $slug,
        default => '/pages/' . $slug,
    };
}

/**
 * Описание для `<meta name="description">` статической страницы —
 * `content_pages` не хранит отдельного поля под сниппет (`database.md`:
 * только `title`/`body`), а `body` — маркетинговый текст произвольной
 * длины/формата, не годится под 160 символов напрямую. Заголовок
 * страницы уже сформулирован коротко Администратором, этого достаточно
 * для устранения дубля описания между страницами (`/audit-seo`).
 */
function defaultPageDescription(string $title): string
{
    return $title . ' — ДомФорм, мебель на заказ в Краснодаре и крае.';
}

function renderContentBody(string $body): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $blocks     = preg_split('/\n\s*\n/', trim($normalized)) ?: [];

    $html = [];
    foreach ($blocks as $block) {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $block)),
            static fn (string $line): bool => $line !== ''
        ));
        if ($lines === []) {
            continue;
        }

        $html[] = renderContentBlock($lines);
    }

    return implode("\n", $html);
}

/**
 * Один блок — одна из трёх форм: заголовок (первая строка начинается
 * с `## `; строки сразу под ним без пустой строки рендерятся как
 * следующий блок — Менеджеру не нужно помнить про отступ после
 * заголовка), список (все строки начинаются с `- `) или абзац (всё
 * остальное).
 */
function renderContentBlock(array $lines): string
{
    if (str_starts_with($lines[0], CONTENT_HEADING_PREFIX)) {
        $heading = trim(substr($lines[0], strlen(CONTENT_HEADING_PREFIX)));
        $rest    = array_slice($lines, 1);
        $html    = '<h2>' . e($heading) . '</h2>';

        return $rest === [] ? $html : $html . "\n" . renderContentBlock($rest);
    }

    $isList = array_reduce(
        $lines,
        static fn (bool $carry, string $line): bool => $carry && str_starts_with($line, CONTENT_LIST_PREFIX),
        true
    );

    if ($isList) {
        $items = array_map(
            static fn (string $line): string => '<li>' . e(trim(substr($line, strlen(CONTENT_LIST_PREFIX)))) . '</li>',
            $lines
        );

        return '<ul>' . implode('', $items) . '</ul>';
    }

    return '<p>' . implode('<br>', array_map('e', $lines)) . '</p>';
}
