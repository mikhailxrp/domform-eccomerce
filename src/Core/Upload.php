<?php

declare(strict_types=1);

/**
 * Проверка загружаемых фото Вариантов (`FR-ADM-001`, Таск 9 Фазы 4) —
 * чистая функция без обращения к файловой системе, MIME определяется
 * снаружи (`FileUpload.php`, `finfo`) и передаётся сюда параметром.
 *
 * Константы определены здесь, а не в `config/config.php`: тот не
 * подключается в `tests/bootstrap.php` (тот же приём, что
 * `CART_MAX_QUANTITY` в `Core/Cart.php`).
 */

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

const UPLOAD_ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Максимальная сторона фото товара после ресайза (`FileUpload.php`,
 * `resizeImageIfNeeded()`) — 1600px с запасом для полноэкранного показа
 * на карточке Товара (`product/show.php`), не только миниатюры.
 */
const PRODUCT_IMAGE_MAX_DIMENSION = 1600;

/**
 * Максимальная сторона исходного загружаемого фото в пикселях —
 * ограничивает файл, который декодирует GD в `resizeImageIfNeeded()`.
 * Без этого предела вес файла (`UPLOAD_MAX_BYTES`) не защищает от
 * decompression bomb: однотонный PNG/WEBP огромного разрешения весит
 * несколько сотен КБ на диске, но при декодировании GD аллоцирует
 * `ширина * высота * 4` байт в память — на shared-хостинге этого
 * достаточно, чтобы упереться в `memory_limit` и уронить запрос.
 */
const UPLOAD_MAX_DIMENSION = 8000;

function uploadExtensionForMime(string $mime): ?string
{
    return UPLOAD_ALLOWED_MIME[$mime] ?? null;
}

/**
 * `$file` — элемент `$_FILES`. `$detectedMime` — результат `finfo` по
 * содержимому временного файла (не расширение и не `$file['type']`,
 * который присылает браузер и которому нельзя доверять). `$dimensions`
 * — `[width, height]` из `getimagesize()` (`FileUpload.php`,
 * `detectImageDimensions()`) либо `null`, если размер не определён —
 * тем же приёмом, что и `$detectedMime`, чтение файла остаётся снаружи.
 */
function validateUploadedImage(array $file, string $detectedMime, ?array $dimensions = null): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return 'Выберите файл.';
    }

    if ($error !== UPLOAD_ERR_OK) {
        return 'Ошибка загрузки файла. Попробуйте ещё раз.';
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > UPLOAD_MAX_BYTES) {
        return 'Файл слишком большой (максимум ' . (int) (UPLOAD_MAX_BYTES / 1024 / 1024) . ' МБ).';
    }

    if (uploadExtensionForMime($detectedMime) === null) {
        return 'Недопустимый тип файла — только JPG, PNG или WEBP.';
    }

    if ($dimensions !== null && ($dimensions[0] > UPLOAD_MAX_DIMENSION || $dimensions[1] > UPLOAD_MAX_DIMENSION)) {
        return 'Слишком большое разрешение фото (максимум ' . UPLOAD_MAX_DIMENSION . 'px по стороне).';
    }

    return null;
}
