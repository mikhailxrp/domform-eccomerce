<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Upload.php';
require_once ROOT_PATH . '/src/Core/Logger.php';

/**
 * Определяет MIME по содержимому временного файла (`finfo`), а не по
 * `$file['type']` (присылает браузер, нельзя доверять) и не по
 * расширению имени. Возвращает `''`, если файл недоступен — тот же
 * случай, что `UPLOAD_ERR_*` ≠ `OK`, дальше отфильтровывается
 * `validateUploadedImage()`.
 */
function detectUploadedMime(string $tmpName): string
{
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return '';
    }

    $mime = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    return is_string($mime) ? $mime : '';
}

/**
 * Сохраняет уже провалидированное фото (`validateUploadedImage()`
 * вызывается до этой функции, не внутри неё) в `UPLOAD_PRODUCTS_DIR` со
 * случайным именем — оригинальное имя файла никогда не используется как
 * часть пути. Возвращает веб-путь с ведущим `/` (не зависит от текущего
 * URL, в отличие от относительных путей `assets/...` в сидах каталога —
 * `product/show.php`/`product-card.php`, не в скоупе этого таска).
 */
function storeProductImage(array $file): ?string
{
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $mime    = detectUploadedMime($tmpName);
    $ext     = uploadExtensionForMime($mime);

    if ($ext === null) {
        return null;
    }

    if (!is_dir(UPLOAD_PRODUCTS_DIR) && !mkdir(UPLOAD_PRODUCTS_DIR, 0755, true) && !is_dir(UPLOAD_PRODUCTS_DIR)) {
        logError('Не удалось создать каталог для фото товаров', ['dir' => UPLOAD_PRODUCTS_DIR]);
        return null;
    }

    $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = UPLOAD_PRODUCTS_DIR . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        logError('Не удалось сохранить загруженное фото товара', ['destination' => $destination]);
        return null;
    }

    return '/uploads/products/' . $filename;
}

/**
 * Фото галереи «О компании» (`about_gallery_images`, `ADR-046`) —
 * тот же приём, что `storeProductImage()`, другой каталог
 * (`UPLOAD_CONTENT_DIR`) и префикс веб-пути.
 */
function storeContentImage(array $file): ?string
{
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $mime    = detectUploadedMime($tmpName);
    $ext     = uploadExtensionForMime($mime);

    if ($ext === null) {
        return null;
    }

    if (!is_dir(UPLOAD_CONTENT_DIR) && !mkdir(UPLOAD_CONTENT_DIR, 0755, true) && !is_dir(UPLOAD_CONTENT_DIR)) {
        logError('Не удалось создать каталог для фото контента', ['dir' => UPLOAD_CONTENT_DIR]);
        return null;
    }

    $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = UPLOAD_CONTENT_DIR . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        logError('Не удалось сохранить загруженное фото контента', ['destination' => $destination]);
        return null;
    }

    return '/uploads/content/' . $filename;
}

/**
 * Фото автора отзыва о магазине (`reviews.photo_path`, `ADR-046`) —
 * тот же приём, что `storeProductImage()`, свой каталог
 * (`UPLOAD_REVIEWS_DIR`).
 */
function storeReviewImage(array $file): ?string
{
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $mime    = detectUploadedMime($tmpName);
    $ext     = uploadExtensionForMime($mime);

    if ($ext === null) {
        return null;
    }

    if (!is_dir(UPLOAD_REVIEWS_DIR) && !mkdir(UPLOAD_REVIEWS_DIR, 0755, true) && !is_dir(UPLOAD_REVIEWS_DIR)) {
        logError('Не удалось создать каталог для фото отзывов', ['dir' => UPLOAD_REVIEWS_DIR]);
        return null;
    }

    $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = UPLOAD_REVIEWS_DIR . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        logError('Не удалось сохранить загруженное фото отзыва', ['destination' => $destination]);
        return null;
    }

    return '/uploads/reviews/' . $filename;
}

/**
 * `$path` — веб-путь с ведущим `/`, как возвращают `storeProductImage()`/
 * `storeContentImage()`/`storeReviewImage()`. Каждый разрешённый
 * префикс проверяется через `realpath()` на свой каталог — защита от
 * `..` в подделанном значении, даже если оно сюда никогда не должно
 * попасть не из БД.
 */
function deleteStoredFile(string $path): void
{
    $relative = ltrim($path, '/');

    $allowedPrefixes = [
        'uploads/products/' => UPLOAD_PRODUCTS_DIR,
        'uploads/content/'  => UPLOAD_CONTENT_DIR,
        'uploads/reviews/'  => UPLOAD_REVIEWS_DIR,
    ];

    $uploadDir = null;
    foreach ($allowedPrefixes as $prefix => $dir) {
        if (str_starts_with($relative, $prefix)) {
            $uploadDir = $dir;
            break;
        }
    }

    if ($uploadDir === null) {
        return;
    }

    $filesystemPath = ROOT_PATH . '/public/' . $relative;
    $realPath       = realpath($filesystemPath);
    $realDir        = realpath($uploadDir);

    if ($realPath === false || $realDir === false || !str_starts_with($realPath, $realDir)) {
        return;
    }

    if (is_file($realPath)) {
        unlink($realPath);
    }
}
