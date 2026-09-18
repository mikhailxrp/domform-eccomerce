<?php

declare(strict_types=1);

/** @var string $title */
/** @var array $order строка orders */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Оплата заказа']];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Оплата заказа</h1>
                <?php include ROOT_PATH . '/src/Views/components/breadcrumbs.php'; ?>
            </div>
        </div>
    </div>

    <div class="section section-padding">
        <div class="container">
            <div class="checkout-info">
                <p class="info-header">
                    <i class="fa fa-info-circle"></i>
                    Заказ №<?= e((string) $order['id']) ?>, статус — «<?= e(orderStatusLabel($order['status'])) ?>».
                </p>
                <p class="info-header">
                    <i class="fa fa-exclamation-circle"></i>
                    Это демонстрационный проект — приём оплаты картой на сайте не подключён,
                    реальное списание средств не производится. Для оплаты и согласования
                    деталей заказа свяжитесь с менеджером.
                </p>
            </div>

            <div class="checkout-call">
                <a href="tel:<?= e(SHOP_PHONE_TEL) ?>" class="btn btn-outline-dark"><i class="fa fa-phone"></i> <?= e(SHOP_PHONE) ?></a>
                <a href="<?= e(SHOP_WHATSAPP_URL) ?>" class="btn btn-outline-dark" target="_blank" rel="noopener"><i class="fa fa-whatsapp"></i> Написать в WhatsApp</a>
            </div>

            <div class="single-form">
                <a href="/checkout/success" class="btn btn-dark btn-hover-primary">Вернуться к заказу</a>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
