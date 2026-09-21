<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $activeSection */
/** @var array<int,array> $products строки attachCheapestVariant() — Товары из Избранного */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Избранное']];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Избранное</h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding mt-n6">
        <div class="container">
            <div class="row">
                <div class="col-xl-3 col-md-4">
                    <?php include ROOT_PATH . '/src/Views/components/account-sidebar.php'; ?>
                </div>
                <div class="col-xl-9 col-md-8">
                    <div class="my-account-tab mt-6">
                        <?php if ($products === []): ?>
                            <div class="empty-cart text-center">
                                <h2 class="empty-cart-title">В избранном пока пусто</h2>
                                <p class="empty-cart-icon"><i class="pe-7s-like"></i></p>
                                <p>Добавьте товары из каталога, нажав на сердце на карточке.</p>
                                <a href="/catalog" class="btn btn-primary btn-hover-dark"><i class="fa fa-angle-left"></i> Перейти в каталог</a>
                            </div>
                        <?php else: ?>
                            <div class="cart-table table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="product-thumb">Фото</th>
                                            <th class="product-info">Товар</th>
                                            <th class="product-add-to-cart">В корзину</th>
                                            <th class="product-action">Удалить</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <?php
                                            $productUrl    = '/product/' . $product['slug'];
                                            $imageUrl      = $product['image_path'] !== null ? '/' . ltrim($product['image_path'], '/') : '/assets/images/product/product-01.jpg';
                                            $hasOldPrice   = $product['min_old_price'] !== null;
                                            ?>
                                            <tr class="cart-row">
                                                <td class="product-thumb">
                                                    <a href="<?= e($productUrl) ?>"><img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>"></a>
                                                </td>
                                                <td class="product-info">
                                                    <h6 class="name"><a href="<?= e($productUrl) ?>"><?= e($product['name']) ?></a></h6>
                                                    <?php if ($hasOldPrice): ?>
                                                        <div class="product-prices">
                                                            <span class="old-price"><?= formatPrice($product['min_old_price']) ?></span>
                                                            <span class="sale-price"><?= formatPrice($product['min_price']) ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="price"><?= formatPrice($product['min_price']) ?></p>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="product-add-to-cart">
                                                    <?php if ($product['variant_id'] !== null): ?>
                                                        <form method="post" action="/cart/add">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="variant_id" value="<?= e((string) $product['variant_id']) ?>">
                                                            <?php if ($product['color'] !== null): ?>
                                                                <input type="hidden" name="color" value="<?= e($product['color']) ?>">
                                                            <?php endif; ?>
                                                            <input type="hidden" name="quantity" value="1">
                                                            <button type="submit" class="btn btn-dark btn-hover-primary">В корзину</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="product-action">
                                                    <form method="post" action="/favorites/remove">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                                                        <button type="submit" class="remove" aria-label="Удалить из избранного"><i class="pe-7s-trash"></i></button>
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
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
