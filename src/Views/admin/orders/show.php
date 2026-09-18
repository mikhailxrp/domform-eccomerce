<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, mixed> $order */
/** @var array<int, array<string, mixed>> $items */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$status = $order['status'];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Заказ №<?= e((string) $order['id']) ?></h4>
        <p class="mb-0"><a href="/admin/orders">← К списку заказов</a></p>
    </div>
    <div>
        <?php include ROOT_PATH . '/src/Views/components/admin/order-status-badge.php'; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Клиент</div>
            </div>
            <div class="card-body">
                <span class="badge <?= $order['is_guest'] ? 'bg-secondary' : 'bg-primary' ?> mb-2"><?= $order['is_guest'] ? 'Гость' : 'Покупатель' ?></span>
                <p class="mb-1"><strong>Имя:</strong> <?= e($order['customer_name'] ?? '—') ?></p>
                <p class="mb-1"><strong>Телефон:</strong> <?= e($order['customer_phone'] ?? '—') ?></p>
                <p class="mb-0"><strong>Email:</strong> <?= e($order['customer_email'] ?? '—') ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Получение и оплата</div>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Способ получения:</strong> <?= e(FULFILLMENT_LABELS[$order['fulfillment_method']] ?? $order['fulfillment_method']) ?></p>
                <?php if ($order['fulfillment_method'] === FULFILLMENT_DELIVERY): ?>
                    <p class="mb-1"><strong>Адрес:</strong> <?= e($order['delivery_address'] ?? '—') ?></p>
                <?php endif; ?>
                <p class="mb-1"><strong>Стоимость доставки:</strong> <?= $order['shipping_cost'] !== null ? e(formatPrice($order['shipping_cost'])) : '—' ?></p>
                <p class="mb-1"><strong>Способ оплаты:</strong> <?= e(PAYMENT_METHOD_LABELS[$order['payment_method']] ?? $order['payment_method']) ?></p>
                <p class="mb-1"><strong>Статус оплаты:</strong> <?= e(PAYMENT_STATUS_LABELS[$order['payment_status']] ?? $order['payment_status']) ?></p>
                <p class="mb-1"><strong>Внесена предоплата:</strong> <?= $order['prepaid_amount'] !== null ? e(formatPrice($order['prepaid_amount'])) : '—' ?></p>
                <p class="mb-0"><strong>Сумма заказа:</strong> <?= e(formatPrice($order['total'])) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Позиции</div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered text-nowrap w-100">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Артикул</th>
                        <th>Материал</th>
                        <th>Механизм</th>
                        <th>Цвет</th>
                        <th>Цена</th>
                        <th>Кол-во</th>
                        <th>Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td><?= e($item['variant_sku']) ?></td>
                            <td><?= e($item['variant_material']) ?></td>
                            <td><?= e($item['variant_mechanism'] ?? '—') ?></td>
                            <td><?= e($item['variant_color'] ?? '—') ?></td>
                            <td><?= e(formatPrice($item['price'])) ?></td>
                            <td><?= e((string) $item['quantity']) ?></td>
                            <td><?= e(formatPrice(bcmul((string) $item['price'], (string) $item['quantity'], 2))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Комментарий покупателя</div>
    </div>
    <div class="card-body">
        <p class="mb-0"><?= nl2br(e($order['comment'])) ?></p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Хронология</div>
    </div>
    <div class="card-body">
        <p class="mb-1"><strong>Создан:</strong> <?= e(date('d.m.Y H:i', strtotime((string) $order['created_at']))) ?></p>
        <p class="mb-1"><strong>Обновлён:</strong> <?= e(date('d.m.Y H:i', strtotime((string) $order['updated_at']))) ?></p>
        <p class="mb-0"><strong>Доставлен/собран:</strong> <?= $order['delivered_at'] !== null ? e(date('d.m.Y H:i', strtotime((string) $order['delivered_at']))) : '—' ?></p>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
