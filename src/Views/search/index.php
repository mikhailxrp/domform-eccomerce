<?php

declare(strict_types=1);

/** @var string $q */
/** @var array<int,array> $products */
/** @var array $filters ['sort' => string] */
/** @var string $resetUrl */
/** @var array $pagination */
/** @var array<int,string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

$title = 'Поиск';

include ROOT_PATH . '/src/Views/layout/header.php';

$sort        = $filters['sort'];
$sortOptions = [
    'newest'     => 'Сначала новые',
    'price_asc'  => 'Цена по возрастанию',
    'price_desc' => 'Цена по убыванию',
];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>
    <div class="section section-padding page-content-offset">
        <div class="container">
            <h1 class="catalog__title">Результаты поиска: «<?= e($q) ?>»</h1>

            <form id="search-sort-form" method="get" action="/search">
                <input type="hidden" name="q" value="<?= e($q) ?>">
            </form>

            <div class="shop-top-bar">
                <div class="shop-text">
                    <p><span><?= e((string) count($products)) ?></span> из <span><?= e((string) $pagination['total']) ?></span> товаров</p>
                </div>
                <div class="shop-sort">
                    <label for="search-sort" class="visually-hidden">Сортировка</label>
                    <select name="sort" id="search-sort" class="nice_select" form="search-sort-form">
                        <?php foreach ($sortOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($products === []): ?>
                <?php include ROOT_PATH . '/src/Views/components/empty-state.php'; ?>
            <?php else: ?>
                <div class="shop-product-wrapper">
                    <div class="row">
                        <?php $viewMode = 'grid'; ?>
                        <?php foreach ($products as $product): ?>
                            <?php include ROOT_PATH . '/src/Views/components/product-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
