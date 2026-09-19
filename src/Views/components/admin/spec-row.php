<?php

declare(strict_types=1);

/**
 * Одна строка характеристики «название — значение» (`product_specs`,
 * Таск 8 Фазы 4) — тот же приём `$index`/`__INDEX__`, что
 * `variant-row.php`.
 */

/** @var int|string $index */
/** @var string $specName */
/** @var string $specValue */
/** @var array<string, bool> $rowErrors */

$fieldName = static fn (string $field): string => "specs[{$index}][{$field}]";
$fieldId   = static fn (string $field): string => "spec-{$field}-{$index}";
?>
<div class="row g-2 mb-2 align-items-end" data-spec-row>
    <div class="col-md-4">
        <label for="<?= e($fieldId('name')) ?>" class="form-label">Название</label>
        <input type="text" id="<?= e($fieldId('name')) ?>" name="<?= e($fieldName('name')) ?>" class="form-control <?= !empty($rowErrors['name']) ? 'is-invalid' : '' ?>" value="<?= e($specName) ?>" placeholder="Ширина">
        <?php if (!empty($rowErrors['name'])): ?>
            <div class="invalid-feedback">Укажите название.</div>
        <?php endif; ?>
    </div>
    <div class="col-md-5">
        <label for="<?= e($fieldId('value')) ?>" class="form-label">Значение</label>
        <input type="text" id="<?= e($fieldId('value')) ?>" name="<?= e($fieldName('value')) ?>" class="form-control <?= !empty($rowErrors['value']) ? 'is-invalid' : '' ?>" value="<?= e($specValue) ?>" placeholder="80 см">
        <?php if (!empty($rowErrors['value'])): ?>
            <div class="invalid-feedback">Укажите значение.</div>
        <?php endif; ?>
    </div>
    <div class="col-md-3">
        <button type="button" class="btn btn-sm btn-outline-danger" data-spec-row-remove>Убрать</button>
    </div>
</div>
