<?php

declare(strict_types=1);

/** @var string $title */
/** @var array|null $category */
/** @var array<int,array> $breadcrumbs */
/** @var array<int,array> $products */
/** @var string $sort */
/** @var string $path */
/** @var array $pagination */
/** @var array<int,string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

include ROOT_PATH . '/src/Views/layout/header.php';

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
            <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>

            <h1 class="catalog__title"><?= e($category['name'] ?? 'Каталог') ?></h1>
            <?php if (($category['description'] ?? '') !== ''): ?>
                <p class="catalog__description"><?= e($category['description']) ?></p>
            <?php endif; ?>

            <div class="shop-top-bar">
                <div class="shop-text">
                    <p><span><?= e((string) count($products)) ?></span> из <span><?= e((string) $pagination['total']) ?></span> товаров</p>
                </div>
                <div class="shop-sort">
                    <form method="get" action="<?= e($path) ?>">
                        <label for="catalog-sort" class="visually-hidden">Сортировка</label>
                        <select name="sort" id="catalog-sort" class="nice_select">
                            <?php foreach ($sortOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Применить</button>
                    </form>
                </div>
            </div>

            <?php if ($products === []): ?>
                <p>В этой категории пока нет товаров.</p>
            <?php else: ?>
                <div class="shop-product-wrapper">
                    <div class="row">
                        <?php foreach ($products as $product): ?>
                            <?php include ROOT_PATH . '/src/Views/components/product-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
