<?php

declare(strict_types=1);

/**
 * Сетка карточек фото одного Варианта — вынесена из `variant-photos.php`
 * (Таск 9 Фазы 4), чтобы Controller мог отрендерить тот же HTML в ответ
 * на AJAX-действие (загрузка/обновление/удаление/«сделать главным»,
 * доработка «множественная загрузка без перезагрузки»): и обычный
 * рендер формы, и JSON-ответ используют один и тот же partial, разметка
 * карточки не дублируется в JS.
 */

/** @var int $productId */
/** @var int $variantId */
/** @var array<int, array<string, mixed>> $images */

$baseUrl = '/admin/products/' . $productId . '/variants/' . $variantId . '/images';
?>
<?php if ($images === []): ?>
    <p class="text-muted small mb-0" data-variant-photo-empty>Фото ещё не загружены.</p>
<?php else: ?>
    <div class="row g-2">
        <?php foreach ($images as $image): ?>
            <?php $imageId = (int) $image['id']; ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <div class="card variant-photo-card h-100">
                    <img src="<?= e('/' . ltrim((string) $image['path'], '/')) ?>" class="card-img-top variant-photo-card__image" alt="<?= e((string) ($image['color'] ?? '')) ?>">
                    <div class="card-body p-2">
                        <form method="post" action="<?= e($baseUrl . '/' . $imageId) ?>" class="mb-1" data-variant-photo-form>
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
                                <form method="post" action="<?= e($baseUrl . '/' . $imageId . '/main') ?>" class="flex-fill" data-variant-photo-form>
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Главным</button>
                                </form>
                            <?php endif; ?>

                            <form method="post" action="<?= e($baseUrl . '/' . $imageId . '/remove') ?>" class="flex-fill" data-variant-photo-form data-variant-photo-form-confirm="Удалить фото?">
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
