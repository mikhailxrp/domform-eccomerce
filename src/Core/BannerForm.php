<?php

declare(strict_types=1);

/**
 * Чистая валидация формы баннера слайдера (`banners`, `FR-ADM-003`
 * п. 2, Таск 7 Фазы 8) — без обращения к БД, по образцу `Core/Review.php`.
 */

const BANNER_TITLE_MAX_LENGTH = 200;

/**
 * `$input['sort_order']` — уже приведено контроллером к `int`
 * (`(int) input('sort_order', 0)`, по образцу
 * `AdminCategoryController::categoryInputFromRequest()`), поэтому
 * здесь только проверка диапазона, без `is_numeric()`.
 *
 * Ссылка необязательна (`banners.link NULL` — баннер без ссылки); если
 * указана — только относительный путь (`/...`) или полный `https://`
 * адрес, никаких `javascript:`/`http://` (тот же принцип, что
 * `validateSettingsInput()` для `map_embed_url`, `Core/Settings.php`).
 */
function validateBannerInput(array $input, bool $hasImage): array
{
    $title = (string) ($input['title'] ?? '');
    $link  = (string) ($input['link'] ?? '');

    return [
        'title'      => mb_strlen($title) > BANNER_TITLE_MAX_LENGTH,
        'link'       => $link !== '' && !str_starts_with($link, '/') && !str_starts_with($link, 'https://'),
        'sort_order' => (int) ($input['sort_order'] ?? 0) < 0,
        'image'      => !$hasImage,
    ];
}
