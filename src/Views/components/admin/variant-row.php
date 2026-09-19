<?php

declare(strict_types=1);

/**
 * Один блок Варианта в форме Товара (`FR-ADM-001`, Таск 8 Фазы 4) —
 * без `<form>` (страница даёт одну общую форму), тот же приём
 * `$index`/`__INDEX__`, что `variant-picker-fields.php` (Таск 5):
 * `null` не используется здесь — Вариант всегда часть повторяющегося
 * списка, даже когда он единственный.
 */

/** @var int|string $index */
/** @var int $id */
/** @var string $sku */
/** @var string $material */
/** @var string $mechanismType */
/** @var string $price */
/** @var string $productionTime */
/** @var bool $isShowroomSample */
/** @var string $discountPercent */
/** @var bool $variantIsActive */
/** @var array<string, bool> $rowErrors */

$fieldName = static fn (string $field): string => "variants[{$index}][{$field}]";
$fieldId   = static fn (string $field): string => "variant-{$field}-{$index}";
$skuInvalid = !empty($rowErrors['sku']) || !empty($rowErrors['sku_duplicate']) || !empty($rowErrors['sku_taken']);
?>
<div class="row g-2 mb-2" data-variant-row>
    <input type="hidden" name="<?= e($fieldName('id')) ?>" value="<?= $id > 0 ? e((string) $id) : '' ?>">

    <div class="col-md-3">
        <label for="<?= e($fieldId('sku')) ?>" class="form-label">Артикул</label>
        <input type="text" id="<?= e($fieldId('sku')) ?>" name="<?= e($fieldName('sku')) ?>" class="form-control <?= $skuInvalid ? 'is-invalid' : '' ?>" value="<?= e($sku) ?>">
        <?php if (!empty($rowErrors['sku'])): ?>
            <div class="invalid-feedback">Введите артикул.</div>
        <?php elseif (!empty($rowErrors['sku_duplicate'])): ?>
            <div class="invalid-feedback">Повторяется в этой форме.</div>
        <?php elseif (!empty($rowErrors['sku_taken'])): ?>
            <div class="invalid-feedback">Занят другим товаром.</div>
        <?php endif; ?>
    </div>

    <div class="col-md-2">
        <label for="<?= e($fieldId('material')) ?>" class="form-label">Материал</label>
        <input type="text" id="<?= e($fieldId('material')) ?>" name="<?= e($fieldName('material')) ?>" class="form-control <?= !empty($rowErrors['material']) ? 'is-invalid' : '' ?>" value="<?= e($material) ?>">
        <?php if (!empty($rowErrors['material'])): ?>
            <div class="invalid-feedback">Укажите материал.</div>
        <?php endif; ?>
    </div>

    <div class="col-md-2">
        <label for="<?= e($fieldId('mechanism')) ?>" class="form-label">Механизм (если есть)</label>
        <input type="text" id="<?= e($fieldId('mechanism')) ?>" name="<?= e($fieldName('mechanism_type')) ?>" class="form-control" value="<?= e($mechanismType) ?>">
    </div>

    <div class="col-md-1">
        <label for="<?= e($fieldId('price')) ?>" class="form-label">Цена</label>
        <input type="text" id="<?= e($fieldId('price')) ?>" name="<?= e($fieldName('price')) ?>" class="form-control <?= !empty($rowErrors['price']) ? 'is-invalid' : '' ?>" value="<?= e($price) ?>" placeholder="35000">
        <?php if (!empty($rowErrors['price'])): ?>
            <div class="invalid-feedback">Число &gt; 0.</div>
        <?php endif; ?>
    </div>

    <div class="col-md-2">
        <label for="<?= e($fieldId('production-time')) ?>" class="form-label">Срок изготовления</label>
        <input type="text" id="<?= e($fieldId('production-time')) ?>" name="<?= e($fieldName('production_time')) ?>" class="form-control <?= !empty($rowErrors['production_time']) ? 'is-invalid' : '' ?>" value="<?= e($productionTime) ?>" placeholder="2-3 недели">
        <?php if (!empty($rowErrors['production_time'])): ?>
            <div class="invalid-feedback">Укажите срок.</div>
        <?php endif; ?>
    </div>

    <div class="col-md-1">
        <label for="<?= e($fieldId('discount')) ?>" class="form-label">Скидка, %</label>
        <input type="text" id="<?= e($fieldId('discount')) ?>" name="<?= e($fieldName('discount_percent')) ?>" class="form-control <?= !empty($rowErrors['discount_percent']) ? 'is-invalid' : '' ?>" value="<?= e($discountPercent) ?>">
        <?php if (!empty($rowErrors['discount_percent'])): ?>
            <div class="invalid-feedback">0–99.99.</div>
        <?php endif; ?>
    </div>

    <div class="col-md-1 d-flex flex-column justify-content-end">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="<?= e($fieldName('is_showroom_sample')) ?>" value="1" id="<?= e($fieldId('sample')) ?>" <?= $isShowroomSample ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= e($fieldId('sample')) ?>">Образец</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="<?= e($fieldName('is_active')) ?>" value="1" id="<?= e($fieldId('active')) ?>" <?= $variantIsActive ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= e($fieldId('active')) ?>">Активен</label>
        </div>
    </div>

    <div class="col-12">
        <button type="button" class="btn btn-sm btn-outline-danger" data-variant-row-remove>Убрать Вариант</button>
    </div>
</div>
