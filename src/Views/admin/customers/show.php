<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, mixed> $customer */
/** @var array<int, array<string, mixed>> $orders */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($customer['name'] !== null && $customer['name'] !== '' ? $customer['name'] : 'Без имени') ?></h4>
        <p class="mb-0"><a href="/admin/customers">← К списку клиентов</a></p>
    </div>
    <div>
        <span class="badge <?= $customer['type'] === 'user' ? 'bg-primary' : 'bg-secondary' ?>">
            <?= $customer['type'] === 'user' ? 'Аккаунт' : 'Гость' ?>
        </span>
    </div>
</div>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Контакты</div>
    </div>
    <div class="card-body">
        <p class="mb-1"><strong>Телефон:</strong> <?= e((string) ($customer['phone'] ?? '—')) ?></p>
        <p class="mb-1"><strong>Email:</strong> <?= e((string) ($customer['email'] ?? '') !== '' ? (string) $customer['email'] : '—') ?></p>
        <p class="mb-0"><strong><?= $customer['type'] === 'user' ? 'Регистрация' : 'Первый заказ' ?>:</strong>
            <?= e(date('d.m.Y', strtotime((string) $customer['created_at']))) ?>
        </p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Заказы</div>
    </div>
    <div class="card-body">
        <?php if ($orders === []): ?>
            <p class="text-muted text-center py-5 mb-0">У клиента нет Заказов.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Сумма</th>
                            <th>Статус оплаты</th>
                            <th>Статус</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php $status = $order['status']; ?>
                            <tr class="admin-orders-table__row" data-href="/admin/orders/<?= e((string) $order['id']) ?>">
                                <td><a href="/admin/orders/<?= e((string) $order['id']) ?>">№<?= e((string) $order['id']) ?></a></td>
                                <td><?= e(formatPrice($order['total'])) ?></td>
                                <td><?= e(PAYMENT_STATUS_LABELS[$order['payment_status']] ?? $order['payment_status']) ?></td>
                                <td><?php include ROOT_PATH . '/src/Views/components/admin/order-status-badge.php'; ?></td>
                                <td><?= e(date('d.m.Y H:i', strtotime((string) $order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
