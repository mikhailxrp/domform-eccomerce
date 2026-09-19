<?php

declare(strict_types=1);

/** @var array $product */
/** @var string $viewMode грид ('grid', по умолчанию) или список ('list') — Таск 3 */

$productUrl = '/product/' . $product['slug'];
$metaText   = trim(
    ($product['material'] ?? '') . ($product['material'] !== null && $product['color'] !== null ? ', ' : '') . ($product['color'] ?? '')
);
$isListView = ($viewMode ?? 'grid') === 'list';

// Фото тем из сидов (product-details/*) не рассчитаны на пропорции
// мини-карточки — заглушка из набора темы под сетку каталога
// (assets/images/product/, 13 файлов), детерминированно по id товара.
// Реальное фото с Таска 9 Фазы 4 (`uploads/products/...`) показывается
// вместо заглушки; сидовые пути (`assets/images/...`) её не заменяют —
// решение зафиксировано при планировании таска.
$placeholderNumber = str_pad((string) ((($product['id'] - 1) % 13) + 1), 2, '0', STR_PAD_LEFT);
$uploadedImagePath = $product['image_path'] ?? null;
$isUploadedPhoto    = $uploadedImagePath !== null && str_starts_with(ltrim($uploadedImagePath, '/'), 'uploads/products/');
$imageUrl           = $isUploadedPhoto
    ? '/' . ltrim($uploadedImagePath, '/')
    : '/assets/images/product/product-' . $placeholderNumber . '.jpg';

ob_start();
?>
<ul class="product-meta">
    <li><a class="action" href="<?= e($productUrl) ?>" aria-label="Открыть товар"><i class="pe-7s-search"></i></a></li>
    <?php if ($product['variant_id'] !== null): ?>
        <li>
            <form method="post" action="/cart/add">
                <?= csrfField() ?>
                <input type="hidden" name="variant_id" value="<?= e((string) $product['variant_id']) ?>">
                <?php if ($product['color'] !== null): ?>
                    <input type="hidden" name="color" value="<?= e($product['color']) ?>">
                <?php endif; ?>
                <input type="hidden" name="quantity" value="1">
                <button class="action" type="submit" aria-label="В корзину"><i class="pe-7s-shopbag"></i></button>
            </form>
        </li>
    <?php endif; ?>
</ul>
<?php
$productMeta = ob_get_clean();
?>

<?php if ($isListView): ?>
    <div class="single-product-02 product-list">
        <div class="product-images">
            <a href="<?= e($productUrl) ?>">
                <img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>">
            </a>
            <?= $productMeta ?>
        </div>
        <div class="product-content">
            <h4 class="title"><a href="<?= e($productUrl) ?>"><?= e($product['name']) ?></a></h4>
            <div class="price">
                <span class="sale-price">от <?= formatPrice($product['min_price']) ?></span>
            </div>
            <?php if ($metaText !== ''): ?>
                <p class="product-card__meta"><?= e($metaText) ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="col-lg-4 col-sm-6">
        <div class="single-product">
            <a href="<?= e($productUrl) ?>">
                <img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>">
            </a>
            <div class="product-content">
                <h4 class="title"><a href="<?= e($productUrl) ?>"><?= e($product['name']) ?></a></h4>
                <div class="price">
                    <span class="sale-price">от <?= formatPrice($product['min_price']) ?></span>
                </div>
                <?php if ($metaText !== ''): ?>
                    <p class="product-card__meta"><?= e($metaText) ?></p>
                <?php endif; ?>
            </div>
            <?= $productMeta ?>
        </div>
    </div>
<?php endif; ?>
