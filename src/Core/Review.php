<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';

/**
 * Чистая нормализация/валидация формы отзыва (`FR-CARD-006`, Таск 4
 * Фазы 6) и расчёт среднего рейтинга — без обращения к БД, по образцу
 * `Core/Checkout.php`. Модерация (`status`) — `Core/Review.php` не
 * знает ролей, только сами значения статуса и их подписи.
 */

const REVIEW_STATUS_PENDING  = 'pending';
const REVIEW_STATUS_APPROVED = 'approved';
const REVIEW_STATUS_REJECTED = 'rejected';

const REVIEW_STATUS_LABELS = [
    REVIEW_STATUS_PENDING  => 'На модерации',
    REVIEW_STATUS_APPROVED => 'Одобрен',
    REVIEW_STATUS_REJECTED => 'Отклонён',
];

const REVIEW_TEXT_MAX_LENGTH = 2000;

function reviewStatusLabel(string $status): string
{
    return REVIEW_STATUS_LABELS[$status] ?? $status;
}

function normalizeReviewInput(array $input): array
{
    $rating = $input['rating'] ?? null;

    return [
        'name'   => trim((string) ($input['name'] ?? '')),
        'email'  => mb_strtolower(trim((string) ($input['email'] ?? '')), 'UTF-8'),
        'rating' => is_numeric($rating) ? (int) $rating : null,
        'text'   => trim((string) ($input['text'] ?? '')),
    ];
}

/**
 * `$input` — результат `normalizeReviewInput()`. Границы длины имени
 * повторяют колонку `reviews.name VARCHAR(150)` (`database.md`);
 * `email` — `VARCHAR(150)`, уже ограничено внутри `validateEmail()`.
 * Верхняя граница текста — не из ТЗ (там не задана), собственное
 * решение против злоупотребления при `text TEXT` без лимита в БД.
 */
function validateReviewInput(array $input): array
{
    $nameLength = mb_strlen($input['name']);

    return [
        'name'   => $nameLength < 2 || $nameLength > 150,
        'email'  => !validateEmail($input['email']),
        'rating' => !in_array($input['rating'], [1, 2, 3, 4, 5], true),
        'text'   => $input['text'] === '' || mb_strlen($input['text']) > REVIEW_TEXT_MAX_LENGTH,
    ];
}

/**
 * Средний рейтинг по уже полученным строкам (`getApprovedProductReviews()`)
 * — один проход по массиву в PHP, отдельный `SELECT AVG(...)` не нужен:
 * список для показа и так читается целиком. Округление до 1 знака —
 * тот же уровень точности, что звёздный виджет умеет отразить визуально.
 */
function averageRating(array $reviews): ?string
{
    if ($reviews === []) {
        return null;
    }

    $sum = 0;
    foreach ($reviews as $review) {
        $sum += (int) $review['rating'];
    }

    return number_format($sum / count($reviews), 1, '.', '');
}
