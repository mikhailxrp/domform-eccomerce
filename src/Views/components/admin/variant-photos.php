<?php

declare(strict_types=1);

/**
 * Фото одного Варианта (`variant_images`, Таск 9 Фазы 4) — рендерится
 * отдельными `<form>` вне общей формы Товара (`admin/products/form.php`
 * включает этот компонент **после** её `</form>`): вложенные `<form>`
 * в HTML недопустимы, а загрузка требует `multipart/form-data`.
 * Доступно только для уже сохранённого Варианта — `$variant['id']`
 * всегда > 0 здесь (форма Товара сама решает, каким Вариантам это
 * показывать).
 *
 * Множественная AJAX-загрузка (доработка после Таска 9) — прогрессивное
 * улучшение: `admin.js` сам добавляет `multiple` инпуту и перехватывает
 * `submit`, отправляя каждый файл отдельным fetch-запросом на тот же
 * `uploadImage()` (Controller как принимал один файл за запрос, так и
 * принимает — просто ответ JSON вместо redirect при AJAX). Без JS форма
 * отправляет один выбранный файл как раньше — полноценная деградация,
 * не заглушка.
 */

/** @var int $productId */
/** @var array<string, mixed> $variant */

$variantId = (int) $variant['id'];
$images    = $variant['images'] ?? [];
$baseUrl   = '/admin/products/' . $productId . '/variants/' . $variantId . '/images';
?>
<div class="border rounded p-3 mb-3" data-variant-photos data-product-id="<?= $productId ?>" data-variant-id="<?= $variantId ?>">
    <h6 class="mb-3"><?= e($variant['sku']) ?> — <?= e($variant['material']) ?></h6>

    <div data-variant-photo-grid>
        <?php include ROOT_PATH . '/src/Views/components/admin/variant-photo-grid.php'; ?>
    </div>

    <div class="d-none alert alert-danger py-2 px-3 mt-2 mb-0 small" data-variant-photo-error></div>

    <div class="mt-2 d-flex flex-column gap-1" data-photo-upload-status></div>

    <form method="post" action="<?= e($baseUrl) ?>" enctype="multipart/form-data" class="row g-2 align-items-end mt-2" data-photo-upload-form>
        <?= csrfField() ?>
        <div class="col-md-3">
            <label class="form-label">Файлы (JPG/PNG/WEBP)</label>
            <div class="variant-photo-dropzone" data-photo-dropzone>
                <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp" required data-photo-upload-input>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Цвет</label>
            <input type="text" name="color" class="form-control">
        </div>
        <div class="col-md-2">
            <div class="form-check mt-4">
                <input type="checkbox" class="form-check-input" name="is_swatch" value="1" id="new-swatch-<?= $variantId ?>">
                <label class="form-check-label" for="new-swatch-<?= $variantId ?>">Образец ткани</label>
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Порядок</label>
            <input type="number" name="sort_order" class="form-control" value="0">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Загрузить</button>
        </div>
    </form>
</div>
