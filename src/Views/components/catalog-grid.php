<?php

declare(strict_types=1);

/** @var array<int,array> $products */
/** @var array $filters */
/** @var array $pagination */
/** @var array<int,string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */
/** @var string $path */
/** @var string $resetUrl */

$sort        = $filters['sort'];
$sortOptions = [
    'newest'     => 'Сначала новые',
    'price_asc'  => 'Цена по возрастанию',
    'price_desc' => 'Цена по убыванию',
];
?>
<div class="shop-top-bar">
    <div class="shop-text">
        <p><span><?= e((string) count($products)) ?></span> из <span><?= e((string) $pagination['total']) ?></span> товаров</p>
    </div>
    <div class="shop-tabs">
        <ul class="nav">
            <li>
                <button
                    type="button"
                    id="catalog-view-grid"
                    class="active"
                    data-bs-toggle="tab"
                    data-bs-target="#catalog-view-pane-grid"
                    data-catalog-view="grid"
                    aria-label="Плитка"
                ><i class="fa fa-th"></i></button>
            </li>
            <li>
                <button
                    type="button"
                    id="catalog-view-list"
                    data-bs-toggle="tab"
                    data-bs-target="#catalog-view-pane-list"
                    data-catalog-view="list"
                    aria-label="Список"
                ><i class="fa fa-list"></i></button>
            </li>
        </ul>
    </div>
    <div class="shop-sort">
        <label for="catalog-sort" class="visually-hidden">Сортировка</label>
        <select name="sort" id="catalog-sort" class="nice_select" form="catalog-filter-form">
            <?php foreach ($sortOptions as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($products === []): ?>
    <?php include ROOT_PATH . '/src/Views/components/empty-state.php'; ?>
<?php else: ?>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="catalog-view-pane-grid">
            <div class="shop-product-wrapper">
                <div class="row">
                    <?php $viewMode = 'grid'; ?>
                    <?php foreach ($products as $product): ?>
                        <?php include ROOT_PATH . '/src/Views/components/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="catalog-view-pane-list">
            <div class="shop-product-wrapper">
                <?php $viewMode = 'list'; ?>
                <?php foreach ($products as $product): ?>
                    <?php include ROOT_PATH . '/src/Views/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>
<?php endif; ?>
