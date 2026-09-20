<?php

declare(strict_types=1);

/** @var string $heading */
/** @var array<int, array<string, mixed>> $products */

if ($products === []) {
    return;
}
?>
<div class="section section-padding">
    <div class="container">
        <div class="section-title">
            <h2 class="title"><?= e($heading) ?></h2>
        </div>
        <div class="shop-product-wrapper">
            <div class="row">
                <?php $viewMode = 'grid'; ?>
                <?php foreach ($products as $product): ?>
                    <?php include ROOT_PATH . '/src/Views/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
