<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';

/**
 * Чистая нормализация/валидация формы обратного звонка (`FR-CNT-001`,
 * Таск 4 Фазы 8) — по образцу `Core/Review.php`. Без обращения к БД;
 * `Model`/Панель управления знают только сами значения статуса.
 */

const CALLBACK_STATUS_NEW       = 'new';
const CALLBACK_STATUS_PROCESSED = 'processed';

const CALLBACK_COMMENT_MAX_LENGTH = 500;

function normalizeCallbackInput(array $input): array
{
    return [
        'name'    => trim((string) ($input['name'] ?? '')),
        'phone'   => normalizePhone((string) ($input['phone'] ?? '')),
        'comment' => trim((string) ($input['comment'] ?? '')),
    ];
}

/**
 * `$input` — результат `normalizeCallbackInput()`. Границы имени — та
 * же `VARCHAR(150)`, что у `reviews.name` (`Core/Review.php`);
 * `comment` необязателен (`callback_requests.comment VARCHAR(500) NULL`).
 */
function validateCallbackInput(array $input): array
{
    $nameLength = mb_strlen($input['name']);

    return [
        'name'    => $nameLength < 2 || $nameLength > 150,
        'phone'   => !validatePhone($input['phone']),
        'comment' => mb_strlen($input['comment']) > CALLBACK_COMMENT_MAX_LENGTH,
    ];
}
