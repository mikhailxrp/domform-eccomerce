<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $products */
/** @var string $statusFilter */
/** @var array $pagination */
/** @var array<int, string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */
/** @var bool $classAvailable */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$specsStatusLabels = [
    'pending'   => 'Требует разбора',
    'confirmed' => 'Подтверждены',
];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">ИИ — Разбор характеристик</h4>
        <p class="mb-0 text-muted">Пакетный разбор описания Товара на характеристики (`FR-AI-001`) — ничего не публикуется без вашего подтверждения</p>
    </div>
</div>

<?php if (!$classAvailable): ?>
    <div class="alert alert-warning" role="alert">
        ИИ-разбор сейчас недоступен — ключ провайдера не настроен или помощник выключен в разделе «ИИ». Характеристики можно ввести вручную в форме Товара.
    </div>
<?php endif; ?>

<div class="card custom-card">
    <div class="card-body">
        <form method="get" action="/admin/ai/specs" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <label for="specs-status-filter" class="form-label">Статус</label>
                <select id="specs-status-filter" name="status" class="form-select">
                    <option value=""<?= $statusFilter === '' ? ' selected' : '' ?>>Все статусы</option>
                    <?php foreach ($specsStatusLabels as $statusValue => $statusLabel): ?>
                        <option value="<?= e($statusValue) ?>"<?= $statusFilter === $statusValue ? ' selected' : '' ?>><?= e($statusLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Показать</button>
            </div>
        </form>

        <?php if ($products === []): ?>
            <p class="text-muted text-center py-5 mb-0">Товаров не найдено.</p>
        <?php else: ?>
            <form method="post" action="/admin/ai/specs/run">
                <?= csrfField() ?>
                <div class="table-responsive">
                    <table class="table table-bordered text-nowrap w-100">
                        <thead>
                            <tr>
                                <th style="width: 1%;"></th>
                                <th>Товар</th>
                                <th>Статус</th>
                                <th>Предложений</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <?php $statusBadgeClass = $product['specs_status'] === 'confirmed' ? 'bg-success' : 'bg-warning'; ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="product_ids[]" value="<?= e((string) $product['id']) ?>" class="form-check-input"<?= $product['specs_status'] !== 'pending' ? ' disabled' : '' ?>>
                                    </td>
                                    <td><a href="/admin/products/<?= e((string) $product['id']) ?>/edit"><?= e($product['name']) ?></a></td>
                                    <td><span class="badge <?= $statusBadgeClass ?>"><?= e($specsStatusLabels[$product['specs_status']] ?? $product['specs_status']) ?></span></td>
                                    <td><?= e((string) $product['suggestions_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($classAvailable): ?>
                    <button type="submit" class="btn btn-primary">Разобрать выбранные</button>
                    <span class="text-muted ms-2">До <?= AI_SPECS_BATCH_MAX ?> Товаров за раз.</span>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
