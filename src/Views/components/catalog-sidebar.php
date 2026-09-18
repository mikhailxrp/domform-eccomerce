<?php

declare(strict_types=1);

/** @var array|null $category */
/** @var array<int,array> $categoryTree */
/** @var array $filterOptions */
/** @var array $filters */
/** @var string $path */
/** @var string $resetUrl */

$leafCategories = [];
foreach ($categoryTree as $rootCategory) {
    if ($rootCategory['children'] !== []) {
        foreach ($rootCategory['children'] as $childCategory) {
            $leafCategories[] = $childCategory;
        }
    } else {
        $leafCategories[] = $rootCategory;
    }
}

$priceMin = $filters['price_min'] ?? $filterOptions['price_min'];
$priceMax = $filters['price_max'] ?? $filterOptions['price_max'];
?>
<div class="sidebar catalog-sidebar">
    <form id="catalog-filter-form" method="get" action="<?= e($path) ?>">
        <?php if ($category === null && $leafCategories !== []): ?>
            <div class="sidebar-widget">
                <h4 class="widget-title">Категория</h4>
                <div class="widget-checkbox widget-categories">
                    <ul class="checkbox-items">
                        <?php foreach ($leafCategories as $leafCategory): ?>
                            <li>
                                <input
                                    type="checkbox"
                                    id="filter-category-<?= e((string) $leafCategory['id']) ?>"
                                    name="category[]"
                                    value="<?= e((string) $leafCategory['id']) ?>"
                                    <?= in_array((int) $leafCategory['id'], $filters['category_ids'], true) ? 'checked' : '' ?>
                                >
                                <label for="filter-category-<?= e((string) $leafCategory['id']) ?>"> <span></span><?= e($leafCategory['name']) ?></label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="sidebar-widget">
            <h4 class="widget-title">Уточнить</h4>
            <div class="widget-checkbox">
                <ul class="checkbox-items">
                    <?php // «В наличии» = Вариант-образец (`FR-CAT-002`, `BR-004`); «Со скидкой» появится здесь же в Фазе 6 (`FR-CAT-010`) ?>
                    <li>
                        <input type="checkbox" id="filter-in-stock" name="in_stock" value="1" <?= $filters['in_stock'] ? 'checked' : '' ?>>
                        <label for="filter-in-stock"> <span></span>В наличии</label>
                    </li>
                </ul>
            </div>
        </div>

        <?php if ($filterOptions['price_max'] > $filterOptions['price_min']): ?>
            <div class="sidebar-widget">
                <h4 class="widget-title">Цена</h4>
                <div class="widget-price">
                    <input
                        id="catalog-price-slider"
                        type="text"
                        data-min="<?= e((string) $filterOptions['price_min']) ?>"
                        data-max="<?= e((string) $filterOptions['price_max']) ?>"
                        data-from="<?= e((string) $priceMin) ?>"
                        data-to="<?= e((string) $priceMax) ?>"
                    >
                    <input type="hidden" id="catalog-price-min" name="price_min" value="<?= e((string) $priceMin) ?>">
                    <input type="hidden" id="catalog-price-max" name="price_max" value="<?= e((string) $priceMax) ?>">
                </div>
            </div>
        <?php endif; ?>

        <?php if ($filterOptions['colors'] !== []): ?>
            <div class="sidebar-widget">
                <h4 class="widget-title">Цвет</h4>
                <div class="widget-checkbox">
                    <ul class="checkbox-items">
                        <?php foreach ($filterOptions['colors'] as $index => $color): ?>
                            <li>
                                <input
                                    type="checkbox"
                                    id="filter-color-<?= e((string) $index) ?>"
                                    name="color[]"
                                    value="<?= e($color) ?>"
                                    <?= in_array($color, $filters['colors'], true) ? 'checked' : '' ?>
                                >
                                <label for="filter-color-<?= e((string) $index) ?>"> <span></span><?= e($color) ?></label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="sidebar-widget">
            <button type="submit" class="btn btn-primary btn-hover-dark d-block">Применить фильтры</button>
            <a class="catalog-reset-link" href="<?= e($resetUrl) ?>">Сбросить фильтры</a>
        </div>
    </form>
</div>
