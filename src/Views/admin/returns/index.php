<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $returns */
/** @var array $pagination */
/** @var array<int, string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$today = date('Y-m-d');
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Возвраты</h4>
        <p class="mb-0 text-muted">Обращения Покупателей по звонку (`FR-RET-001`) — полностью офлайн-процесс, без формы на сайте</p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <?php if ($returns === []): ?>
            <p class="text-muted text-center py-5 mb-0">Возвратов не зафиксировано.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>Заказ</th>
                            <th>Клиент</th>
                            <th>Доставлен</th>
                            <th>Гарантия до</th>
                            <th>Дата обращения</th>
                            <th>Комментарий</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returns as $return): ?>
                            <?php
                            $orderUrl       = '/admin/orders/' . $return['order_id'];
                            $warrantyUntil  = warrantyExpiresAt($return['delivered_at']);
                            $underWarranty  = isUnderWarranty($return['delivered_at'], $today);
                            ?>
                            <tr class="admin-orders-table__row" data-href="<?= e($orderUrl) ?>">
                                <td><a href="<?= e($orderUrl) ?>">№<?= e((string) $return['order_id']) ?></a></td>
                                <td><?= e($return['customer_name'] ?? '—') ?></td>
                                <td><?= $return['delivered_at'] !== null ? e(date('d.m.Y', strtotime((string) $return['delivered_at']))) : '—' ?></td>
                                <td>
                                    <?php if ($warrantyUntil !== null): ?>
                                        <?= e(date('d.m.Y', strtotime($warrantyUntil))) ?>
                                        <span class="badge <?= $underWarranty ? 'bg-success' : 'bg-secondary' ?>"><?= $underWarranty ? 'В пределах' : 'Истекла' ?></span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= e(date('d.m.Y H:i', strtotime((string) $return['created_at']))) ?></td>
                                <td class="text-wrap"><?= $return['note'] !== null && $return['note'] !== '' ? nl2br(e($return['note'])) : '—' ?></td>
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
