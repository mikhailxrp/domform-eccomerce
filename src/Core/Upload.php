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

function uploadExtensionForMime(string $mime): ?string
{
    return UPLOAD_ALLOWED_MIME[$mime] ?? null;
}

/**
 * `$file` — элемент `$_FILES`. `$detectedMime` — результат `finfo` по
 * содержимому временного файла (не расширение и не `$file['type']`,
 * который присылает браузер и которому нельзя доверять).
 */
function validateUploadedImage(array $file, string $detectedMime): ?string
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

    return null;
}
