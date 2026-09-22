<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, mixed> $product */
/** @var array<int|string, string> $old */
/** @var array<int, string> $errors */
/** @var array<string, string> $targetLabels */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$suggestions = $product['suggestions'];
$variants    = $product['variants'];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">ИИ — Ревью характеристик: <?= e($product['name']) ?></h4>
        <p class="mb-0"><a href="/admin/ai/specs">← К очереди разбора</a> · <a href="/admin/products/<?= e((string) $product['id']) ?>/edit">Карточка Товара</a></p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <?php if ($suggestions === []): ?>
            <p class="text-muted">Предложений нет — запустите разбор из очереди или подтвердите вручную, если характеристики уже введены в форме Товара.</p>
        <?php else: ?>
            <form method="post" action="/admin/ai/specs/<?= e((string) $product['id']) ?>/apply">
                <?= csrfField() ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle w-100">
                        <thead>
                            <tr>
                                <th style="width: 1%;"></th>
                                <th>Цель</th>
                                <th>Название</th>
                                <th>Значение</th>
                                <th>Вариант</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suggestions as $suggestion): ?>
                                <?php
                                $id           = (int) $suggestion['id'];
                                $isColor      = $suggestion['target'] === 'color';
                                $isVariant    = in_array($suggestion['target'], ['variant_material', 'variant_mechanism'], true);
                                $currentValue = (string) ($old['value'][$id] ?? $suggestion['value']);
                                $hasError     = isset($errors[$id]);
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!$isColor): ?>
                                            <input type="checkbox" name="accepted[<?= $id ?>]" value="1" class="form-check-input"<?= !empty($old['accepted'][$id]) ? ' checked' : '' ?>>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e($targetLabels[$suggestion['target']] ?? $suggestion['target']) ?>
                                        <?php if ($suggestion['status'] === 'needs_decision'): ?>
                                            <span class="badge bg-warning ms-1">Требует решения</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($suggestion['name']) ?></td>
                                    <td>
                                        <?php if ($isColor): ?>
                                            <span class="text-muted"><?= e($suggestion['value']) ?> (только подсказка — не применяется)</span>
                                        <?php else: ?>
                                            <input type="text" name="value[<?= $id ?>]" value="<?= e($currentValue) ?>" class="form-control<?= $hasError ? ' is-invalid' : '' ?>">
                                            <?php if ($hasError): ?>
                                                <div class="invalid-feedback"><?= e($errors[$id]) ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isVariant): ?>
                                            <select name="variant_id[<?= $id ?>]" class="form-select">
                                                <option value="">— выберите —</option>
                                                <?php foreach ($variants as $variant): ?>
                                                    <option value="<?= e((string) $variant['id']) ?>"<?= (string) ($old['variant_id'][$id] ?? '') === (string) $variant['id'] ? ' selected' : '' ?>><?= e($variant['sku']) ?> — <?= e($variant['material']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary">Применить</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="/admin/ai/specs/<?= e((string) $product['id']) ?>/confirm">
    <?= csrfField() ?>
    <button type="submit" class="btn btn-outline-secondary">Подтвердить вручную (без ИИ)</button>
</form>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
