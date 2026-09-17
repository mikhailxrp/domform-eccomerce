<?php

declare(strict_types=1);

/** @var array<int,array> $variants */

$firstVariant     = $variants[0];
$hasSingleVariant = count($variants) === 1;
?>
<div
    class="product-variant-selector"
    data-variants="<?= e(json_encode($variants, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
    data-preselected="<?= $hasSingleVariant ? e((string) $firstVariant['id']) : '' ?>"
>
    <?php if (!$hasSingleVariant): ?>
        <div class="product-variant-selector__group" role="group" aria-label="Материал">
            <span class="product-variant-selector__label">Материал:</span>
            <?php foreach ($variants as $variant): ?>
                <button type="button" class="product-variant-selector__option" data-variant-id="<?= e((string) $variant['id']) ?>"><?= e($variant['material']) ?></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="product-variant-selector__color-name"<?= $firstVariant['images'] === [] ? ' hidden' : '' ?>>
        Цвет: <strong id="product-color-name"><?= e((string) ($firstVariant['images'][0]['color'] ?? '')) ?></strong>
    </p>

    <p class="product-variant-selector__mechanism"<?= $firstVariant['mechanism_type'] === null ? ' hidden' : '' ?>>
        <?= $firstVariant['mechanism_type'] !== null ? 'Механизм: ' . e($firstVariant['mechanism_type']) : '' ?>
    </p>

    <p class="product-variant-selector__lead-time">
        <?php if ($firstVariant['is_showroom_sample']): ?>
            <strong>Выставочный образец</strong> — готов к выдаче.
        <?php else: ?>
            Срок изготовления: <strong><?= e($firstVariant['production_time']) ?></strong>. Товар изготавливается под заказ.
        <?php endif; ?>
    </p>

    <form method="post" action="/cart/add" class="product-variant-selector__cart-form">
        <?= csrfField() ?>
        <input type="hidden" name="variant_id" value="<?= $hasSingleVariant ? e((string) $firstVariant['id']) : '' ?>" class="product-variant-selector__variant-input">
        <input type="hidden" name="color" value="<?= e((string) ($firstVariant['images'][0]['color'] ?? '')) ?>" class="product-variant-selector__color-input">
        <div class="product-variant-selector__cart-row">
            <div class="product-variant-selector__quantity">
                <label for="product-quantity" class="visually-hidden">Количество</label>
                <input type="number" id="product-quantity" name="quantity" value="1" min="1" max="20">
            </div>
            <button type="submit" class="btn btn-dark btn-hover-primary product-variant-selector__submit">В корзину</button>
        </div>
        <p class="product-variant-selector__hint" role="alert" hidden>Сначала выберите материал.</p>
    </form>
</div>
