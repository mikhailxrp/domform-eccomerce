<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $activeSection */
/** @var array $order строка orders */
/** @var array<int,array> $items строки order_items + line_total */
/** @var string $fulfillmentLabel */
/** @var string $paymentLabel */
/** @var string $paymentStatusLabel */
/** @var string|null $warrantyUntil */
/** @var bool $underWarranty */

include ROOT_PATH . '/src/Views/layout/header.php';

$breadcrumbs = [['name' => 'Заказ №' . $order['id']]];
?>

<main>
    <?php include ROOT_PATH . '/src/Views/components/flash.php'; ?>

    <div class="section page-banner-section page-banner-section--cart">
        <div class="container">
            <div class="page-banner-content">
                <h1 class="title">Заказ №<?= e((string) $order['id']) ?></h1>
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
                        <div class="my-account-order account-wrapper">
                            <h4 class="account-title">
                                Заказ №<?= e((string) $order['id']) ?>
                                <span class="badge <?= e(orderStatusBadgeClass($order['status'])) ?>"><?= e(orderStatusLabel($order['status'])) ?></span>
                            </h4>

                            <p class="mb-1"><strong>Дата:</strong> <?= e(date('d.m.Y H:i', strtotime((string) $order['created_at']))) ?></p>
                            <p class="mb-1"><strong>Способ получения:</strong> <?= e($fulfillmentLabel) ?></p>
                            <?php if ($order['delivery_address'] !== null): ?>
                                <p class="mb-1"><strong>Адрес доставки:</strong> <?= e($order['delivery_address']) ?></p>
                            <?php endif; ?>
                            <p class="mb-1"><strong>Способ оплаты:</strong> <?= e($paymentLabel) ?></p>
                            <p class="mb-1"><strong>Статус оплаты:</strong> <?= e($paymentStatusLabel) ?></p>
                            <p class="mb-1">
                                <strong>Стоимость доставки:</strong>
                                <?= $order['shipping_cost'] !== null ? e(formatPrice($order['shipping_cost'])) : 'уточняется' ?>
                            </p>
                            <?php if (trim((string) $order['comment']) !== ''): ?>
                                <p class="mb-1"><strong>Комментарий:</strong> <?= e($order['comment']) ?></p>
                            <?php endif; ?>

                            <?php if ($order['status'] === ORDER_STATUS_DELIVERED): ?>
                                <p class="mb-3">
                                    <strong>Гарантия до:</strong>
                                    <?php if ($warrantyUntil !== null): ?>
                                        <?= e(date('d.m.Y', strtotime($warrantyUntil))) ?>
                                        <span class="badge <?= $underWarranty ? 'bg-success' : 'bg-secondary' ?>"><?= $underWarranty ? 'В пределах гарантии' : 'Гарантия истекла' ?></span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <div class="account-table text-center mt-30 table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="Product-name">Товар</th>
                                            <th>Кол-во</th>
                                            <th>Цена</th>
                                            <th>Сумма</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td class="Product-name">
                                                    <p class="mb-0"><?= e($item['product_name']) ?></p>
                                                    <p class="text-muted small mb-0">
                                                        Артикул <?= e($item['variant_sku']) ?>, <?= e($item['variant_material']) ?><?php if ($item['variant_mechanism'] !== null): ?>, <?= e($item['variant_mechanism']) ?><?php endif; ?><?php if ($item['variant_color'] !== null): ?>, цвет «<?= e($item['variant_color']) ?>»<?php endif; ?>
                                                    </p>
                                                </td>
                                                <td><?= e((string) $item['quantity']) ?></td>
                                                <td><?= formatPrice($item['price']) ?></td>
                                                <td><?= formatPrice($item['line_total']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td class="Product-name" colspan="3"><p class="mb-0">Итого</p></td>
                                            <td><p class="mb-0"><?= formatPrice($order['total']) ?></p></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="single-form mt-30">
                                <a href="/account/orders" class="btn btn-outline-dark">Назад к заказам</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
