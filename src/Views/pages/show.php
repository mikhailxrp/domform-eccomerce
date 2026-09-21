<?php

declare(strict_types=1);

/** @var string $title */
/** @var array $page строка content_pages */
/** @var string $bodyHtml уже экранированный HTML из renderContentBody() */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => $page['title']]];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title"><?= e($page['title']) ?></h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <article class="information-content">
                        <?php if (!empty($page['image_path'])): ?>
                            <img src="<?= e('/' . ltrim((string) $page['image_path'], '/')) ?>" alt="<?= e($page['title']) ?>" class="img-fluid mb-4">
                        <?php endif; ?>
                        <?php
                        // Единственный вывод без e(): каждый фрагмент уже
                        // экранирован внутри renderContentBody()
                        // (`Core/Content.php`, `ADR-044`).
                        echo $bodyHtml;
                        ?>
                    </article>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
