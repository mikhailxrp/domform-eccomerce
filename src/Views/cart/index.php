<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int,array> $items */
/** @var string $total */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Корзина']];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Корзина</h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding">
        <div class="container">
            <?php if ($items === []): ?>
                <div class="cart-wrapper">
                    <div class="empty-cart text-center">
                        <h2 class="empty-cart-title">В корзине пока пусто</h2>
                        <div class="empty-cart-img">
                            <img src="/assets/images/cart.png" alt="Пустая корзина">
                        </div>
                        <p>Добавьте товары из каталога, чтобы оформить заказ.</p>
                        <a href="/catalog" class="btn btn-primary btn-hover-dark"><i class="fa fa-angle-left"></i> Перейти в каталог</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="cart-wrapper">
                    <div class="cart-table table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="product-thumb">Фото</th>
                                    <th class="product-info">Товар</th>
                                    <th class="product-quantity">Количество</th>
                                    <th class="product-total-price">Сумма</th>
                                    <th class="product-action">Удалить</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php include ROOT_PATH . '/src/Views/components/cart-row.php'; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="cart-btn">
                        <div class="left-btn">
                            <a href="/catalog" class="btn btn-dark btn-hover-primary">Продолжить покупки</a>
                        </div>
                        <div class="right-btn">
                            <form method="post" action="/cart/clear">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-outline-dark">Очистить корзину</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-end">
                    <div class="col-lg-4">
                        <div class="cart-totals">
                            <div class="cart-title">
                                <h4 class="title">Итого</h4>
                            </div>
                            <p class="cart-shipping-note">Стоимость доставки уточняется при подтверждении заказа.</p>
                            <div class="cart-total-table">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <td><p class="value">Сумма</p></td>
                                            <td><p class="price"><?= formatPrice($total) ?></p></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="cart-total-btn">
                                <a href="/checkout" class="btn btn-dark btn-hover-primary btn-block">Оформить заказ</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
