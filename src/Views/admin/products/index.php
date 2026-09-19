<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $products */
/** @var array<int, array<string, mixed>> $categories */
/** @var int $categoryFilter */
/** @var string $statusFilter */
/** @var string $searchQuery */
/** @var array $pagination */
/** @var array<int, string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Товары</h4>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <form method="get" action="/admin/products" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <label for="product-category-filter" class="form-label">Категория</label>
                <select id="product-category-filter" name="category" class="form-select">
                    <option value="">Все категории</option>
                    <?php foreach ($categories as $category): ?>
                        <?php $categoryLabel = $category['parent_name'] !== null ? $category['parent_name'] . ' / ' . $category['name'] : $category['name']; ?>
                        <option value="<?= e((string) $category['id']) ?>"<?= $categoryFilter === $category['id'] ? ' selected' : '' ?>><?= e($categoryLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label for="product-status-filter" class="form-label">Статус</label>
                <select id="product-status-filter" name="status" class="form-select">
                    <option value="">Все статусы</option>
                    <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Активные</option>
                    <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Скрытые</option>
                </select>
            </div>
            <div class="col-sm-6 col-md-4">
                <label for="product-search" class="form-label">Название или артикул</label>
                <input type="text" id="product-search" name="search" class="form-control" value="<?= e($searchQuery) ?>">
            </div>
            <div class="col-sm-2 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Найти</button>
            </div>
        </form>

        <?php if ($products === []): ?>
            <p class="text-muted text-center py-5 mb-0">Товары не найдены.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Вариантов</th>
                            <th>Цена</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $placeholderNumber = str_pad((string) ((($product['id'] - 1) % 13) + 1), 2, '0', STR_PAD_LEFT);
                            $imageUrl           = $product['image_path'] !== null
                                ? '/' . ltrim($product['image_path'], '/')
                                : '/assets/images/product/product-' . $placeholderNumber . '.jpg';

                            $variantCount = (int) $product['variant_count'];
                            $priceLabel   = '—';
                            if ($product['min_price'] !== null) {
                                $priceLabel = bccomp((string) $product['min_price'], (string) $product['max_price'], 2) === 0
                                    ? formatPrice($product['min_price'])
                                    : formatPrice($product['min_price']) . ' – ' . formatPrice($product['max_price']);
                            }
                            ?>
                            <tr>
                                <td><img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>" width="48" height="48" class="rounded" style="object-fit: cover;"></td>
                                <td><?= e($product['name']) ?></td>
                                <td><?= $product['category_name'] !== null ? e($product['category_name']) : '—' ?></td>
                                <td><?= e((string) $variantCount) ?></td>
                                <td><?= e($priceLabel) ?></td>
                                <td>
                                    <span class="badge <?= $product['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $product['is_active'] ? 'Активен' : 'Скрыт' ?></span>
                                    <?php if ($product['has_showroom_sample']): ?>
                                        <span class="badge bg-info">Образец</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex gap-1">
                                    <a href="/admin/products/<?= e((string) $product['id']) ?>/edit" class="btn btn-sm btn-outline-primary">Редактировать</a>
                                    <form method="post" action="/admin/products/<?= e((string) $product['id']) ?>/toggle">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $product['is_active'] ? 'Скрыть' : 'Показать' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
