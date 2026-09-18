<?php

declare(strict_types=1);

/** @var string $title */
/** @var array|null $category */
/** @var array<int,array> $breadcrumbs */
/** @var array<int,array> $categoryTree */
/** @var array $filterOptions */
/** @var array<int,array> $products */
/** @var array $filters */
/** @var string $path */
/** @var string $resetUrl */
/** @var array $pagination */
/** @var array<int,string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

include ROOT_PATH . '/src/Views/layout/header.php';
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>
    <div class="section page-banner-section page-banner-section--catalog">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title"><?= e($category['name'] ?? 'Каталог') ?></h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding-02">
        <div class="container">
            <?php if (($category['description'] ?? '') !== ''): ?>
                <p class="catalog__description"><?= e($category['description']) ?></p>
            <?php endif; ?>

            <div class="row flex-row-reverse">
                <div class="col-lg-9">
                    <div id="catalog-results" role="region" aria-live="polite">
                        <?php include ROOT_PATH . '/src/Views/components/catalog-grid.php'; ?>
                    </div>
                </div>
                <div class="col-lg-3">
                    <?php include ROOT_PATH . '/src/Views/components/catalog-sidebar.php'; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
