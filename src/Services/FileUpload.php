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
 * `[width, height]` загруженного файла до какой-либо декодировки через
 * GD — `getimagesize()` читает только заголовок, не весь файл, поэтому
 * безопасен даже для decompression bomb. `null`, если размер не
 * определён (не изображение / повреждённый файл) — тот же случай, что
 * `''` у `detectUploadedMime()`, дальше отфильтровывается
 * `validateUploadedImage()`.
 */
function detectImageDimensions(string $tmpName): ?array
{
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return null;
    }

    $size = @getimagesize($tmpName);

    return $size !== false ? [$size[0], $size[1]] : null;
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

    resizeImageIfNeeded($destination, $mime, PRODUCT_IMAGE_MAX_DIMENSION);

    return '/uploads/products/' . $filename;
}

/**
 * Уменьшает фото товара до `$maxDimension` по длинной стороне, если
 * снимок крупнее (пропорции сохраняются, апскейл не делается).
 * Требует GD — на части shared-хостингов расширение может быть
 * отключено, поэтому при `!extension_loaded('gd')` или отсутствии
 * нужной `imagecreatefrom*()`/`image*()` пары для конкретного MIME
 * тихо ничего не делает: сам файл уже сохранён `move_uploaded_file()`
 * до вызова этой функции, загрузка не должна падать из-за
 * недоступного ресайза — просто фото останется в исходном размере.
 */
function resizeImageIfNeeded(string $path, string $mime, int $maxDimension): void
{
    if (!extension_loaded('gd')) {
        return;
    }

    $decode = match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? 'imagecreatefromjpeg' : null,
        'image/png'  => function_exists('imagecreatefrompng') ? 'imagecreatefrompng' : null,
        'image/webp' => function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null,
        default      => null,
    };
    $encode = match ($mime) {
        'image/jpeg' => function_exists('imagejpeg') ? 'imagejpeg' : null,
        'image/png'  => function_exists('imagepng') ? 'imagepng' : null,
        'image/webp' => function_exists('imagewebp') ? 'imagewebp' : null,
        default      => null,
    };

    if ($decode === null || $encode === null) {
        return;
    }

    $size = @getimagesize($path);
    if ($size === false) {
        return;
    }

    [$width, $height] = $size;
    if ($width <= 0 || $height <= 0 || ($width <= $maxDimension && $height <= $maxDimension)) {
        return;
    }

    $source = @$decode($path);
    if ($source === false) {
        return;
    }

    $ratio     = min($maxDimension / $width, $maxDimension / $height);
    $newWidth  = max(1, (int) round($width * $ratio));
    $newHeight = max(1, (int) round($height * $ratio));

    $resized = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $mime === 'image/jpeg' ? $encode($resized, $path, 85) : $encode($resized, $path);

    imagedestroy($source);
    imagedestroy($resized);
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
