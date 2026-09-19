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
 */

/** @var int $productId */
/** @var array<string, mixed> $variant */

$variantId     = (int) $variant['id'];
$images        = $variant['images'] ?? [];
$baseUrl       = '/admin/products/' . $productId . '/variants/' . $variantId . '/images';
?>
<div class="border rounded p-3 mb-3" data-variant-photos>
    <h6 class="mb-3"><?= e($variant['sku']) ?> — <?= e($variant['material']) ?></h6>

    <?php if ($images === []): ?>
        <p class="text-muted small">Фото ещё не загружены.</p>
    <?php else: ?>
        <div class="row g-2 mb-3">
            <?php foreach ($images as $image): ?>
                <?php $imageId = (int) $image['id']; ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <div class="card variant-photo-card h-100">
                        <img src="<?= e('/' . ltrim((string) $image['path'], '/')) ?>" class="card-img-top variant-photo-card__image" alt="<?= e((string) ($image['color'] ?? '')) ?>">
                        <div class="card-body p-2">
                            <form method="post" action="<?= e($baseUrl . '/' . $imageId) ?>" class="mb-1">
                                <?= csrfField() ?>
                                <input type="text" name="color" class="form-control form-control-sm mb-1" placeholder="Цвет" value="<?= e((string) ($image['color'] ?? '')) ?>">
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <div class="form-check form-check-sm mb-0">
                                        <input type="checkbox" class="form-check-input" name="is_swatch" value="1" id="swatch-<?= $imageId ?>" <?= (bool) $image['is_swatch'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="swatch-<?= $imageId ?>">Образец</label>
                                    </div>
                                    <input type="number" name="sort_order" class="form-control form-control-sm variant-photo-card__order-input ms-auto" value="<?= e((string) $image['sort_order']) ?>">
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Сохранить</button>
                            </form>

                            <div class="d-flex gap-1">
                                <?php if ((bool) $image['is_main']): ?>
                                    <span class="badge bg-success flex-fill py-2">Главное</span>
                                <?php else: ?>
                                    <form method="post" action="<?= e($baseUrl . '/' . $imageId . '/main') ?>" class="flex-fill">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Главным</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="<?= e($baseUrl . '/' . $imageId . '/remove') ?>" class="flex-fill">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">Удалить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e($baseUrl) ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-3">
            <label class="form-label">Файл (JPG/PNG/WEBP)</label>
            <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
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
