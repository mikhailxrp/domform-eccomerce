<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $orders */
/** @var string $statusFilter */
/** @var string $searchQuery */
/** @var array $pagination */
/** @var array<int, string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Заказы</h4>
        <p class="mb-0 text-muted">Все заказы независимо от источника — сайт или звонок/WhatsApp</p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <form method="get" action="/admin/orders" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <label for="order-status-filter" class="form-label">Статус</label>
                <select id="order-status-filter" name="status" class="form-select">
                    <option value="">Все статусы</option>
                    <?php foreach (ORDER_STATUS_LABELS as $statusValue => $statusLabel): ?>
                        <option value="<?= e($statusValue) ?>"<?= $statusFilter === $statusValue ? ' selected' : '' ?>><?= e($statusLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-4">
                <label for="order-search" class="form-label">№ заказа или телефон</label>
                <input type="text" id="order-search" name="search" class="form-control" value="<?= e($searchQuery) ?>" placeholder="Например, 42 или +7 918…">
            </div>
            <div class="col-sm-2 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Найти</button>
            </div>
            <?php if ($statusFilter !== '' || $searchQuery !== ''): ?>
                <div class="col-sm-2 col-md-2">
                    <a href="/admin/orders" class="btn btn-outline-secondary w-100">Сбросить</a>
                </div>
            <?php endif; ?>
        </form>

        <?php if ($orders === []): ?>
            <p class="text-muted text-center py-5 mb-0">Заказы не найдены.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Клиент</th>
                            <th>Сумма</th>
                            <th>Получение</th>
                            <th>Оплата</th>
                            <th>Статус оплаты</th>
                            <th>Статус</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $customerName = $order['user_id'] !== null ? $order['user_name'] : $order['guest_name'];
                            $status       = $order['status'];
                            ?>
                            <tr>
                                <td><a href="/admin/orders/<?= e((string) $order['id']) ?>">№<?= e((string) $order['id']) ?></a></td>
                                <td><?= e($customerName ?? '—') ?></td>
                                <td><?= e(formatPrice($order['total'])) ?></td>
                                <td><?= e(FULFILLMENT_LABELS[$order['fulfillment_method']] ?? $order['fulfillment_method']) ?></td>
                                <td><?= e(PAYMENT_METHOD_LABELS[$order['payment_method']] ?? $order['payment_method']) ?></td>
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

<?php include ROOT_PATH . '/src/Views/components/pagination.php'; ?>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
