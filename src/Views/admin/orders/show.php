<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, mixed> $order */
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, string> $allowedTransitions */
/** @var bool $canCancel */
/** @var bool $canEditItems */
/** @var array<int, array<string, mixed>> $reserves */
/** @var array<int, array<string, mixed>> $returns */
/** @var array<int, array<string, mixed>> $smsNotifications */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$status  = $order['status'];
$orderId = (string) $order['id'];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Заказ №<?= e($orderId) ?></h4>
        <p class="mb-0"><a href="/admin/orders">← К списку заказов</a></p>
    </div>
    <div>
        <?php include ROOT_PATH . '/src/Views/components/admin/order-status-badge.php'; ?>
    </div>
</div>

<?php if ($allowedTransitions !== [] || $canCancel): ?>
    <div class="card custom-card">
        <div class="card-header">
            <div class="card-title">Действия</div>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
            <?php foreach ($allowedTransitions as $nextStatus): ?>
                <form method="post" action="/admin/orders/<?= e($orderId) ?>/transition">
                    <?= csrfField() ?>
                    <input type="hidden" name="to" value="<?= e($nextStatus) ?>">
                    <button type="submit" class="btn btn-primary"><?= e(orderStatusLabel($nextStatus)) ?></button>
                </form>
            <?php endforeach; ?>

            <?php if ($canCancel): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">Отменить заказ</button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

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

<div class="row">
    <?php if ($order['payment_status'] !== PAYMENT_STATUS_PAID_FULL): ?>
        <div class="col-lg-6">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Оплата</div>
                </div>
                <div class="card-body">
                    <?php if ($order['payment_status'] === PAYMENT_STATUS_UNPAID): ?>
                        <form method="post" action="/admin/orders/<?= e($orderId) ?>/prepaid" class="row g-2 align-items-end">
                            <?= csrfField() ?>
                            <div class="col-auto">
                                <label for="prepaid-amount" class="form-label">Сумма предоплаты</label>
                                <input type="text" id="prepaid-amount" name="amount" class="form-control" placeholder="Например, 10500.00" required>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Отметить предоплату</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/admin/orders/<?= e($orderId) ?>/paid-full">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-primary">Остаток получен</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="col-lg-6">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Стоимость доставки</div>
            </div>
            <div class="card-body">
                <form method="post" action="/admin/orders/<?= e($orderId) ?>/shipping" class="row g-2 align-items-end">
                    <?= csrfField() ?>
                    <div class="col-auto">
                        <label for="shipping-cost" class="form-label">Стоимость (пусто — сбросить)</label>
                        <input type="text" id="shipping-cost" name="shipping_cost" class="form-control" value="<?= $order['shipping_cost'] !== null ? e((string) $order['shipping_cost']) : '' ?>" placeholder="Например, 2500">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
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
                        <?php if ($canEditItems): ?>
                            <th></th>
                        <?php endif; ?>
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
                            <td>
                                <?php if ($canEditItems): ?>
                                    <form method="post" action="/admin/orders/<?= e($orderId) ?>/items/<?= e((string) $item['id']) ?>" class="d-flex gap-1">
                                        <?= csrfField() ?>
                                        <input type="number" name="quantity" class="form-control form-control-sm order-items-table__quantity-input" value="<?= e((string) $item['quantity']) ?>" min="1" required>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Сохранить</button>
                                    </form>
                                <?php else: ?>
                                    <?= e((string) $item['quantity']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e(formatPrice(bcmul((string) $item['price'], (string) $item['quantity'], 2))) ?></td>
                            <?php if ($canEditItems): ?>
                                <td>
                                    <form method="post" action="/admin/orders/<?= e($orderId) ?>/items/<?= e((string) $item['id']) ?>/remove">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($canEditItems): ?>
            <hr>
            <h6>Добавить позицию</h6>
            <?php include ROOT_PATH . '/src/Views/components/admin/variant-picker.php'; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($reserves !== []): ?>
    <?php include ROOT_PATH . '/src/Views/components/admin/reserve-panel.php'; ?>
<?php endif; ?>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Комментарий покупателя</div>
    </div>
    <div class="card-body">
        <p class="mb-0"><?= nl2br(e($order['comment'])) ?></p>
    </div>
</div>

<?php if ($status === ORDER_STATUS_CANCELLED): ?>
    <div class="card custom-card">
        <div class="card-header">
            <div class="card-title">Отмена</div>
        </div>
        <div class="card-body">
            <p class="mb-1"><strong>Комментарий:</strong> <?= $order['cancel_note'] !== null && $order['cancel_note'] !== '' ? nl2br(e($order['cancel_note'])) : '—' ?></p>
            <p class="mb-0"><strong>Предоплата возвращена:</strong> <?= ((int) $order['prepayment_refunded']) === 1 ? 'Да' : 'Нет' ?></p>
        </div>
    </div>
<?php endif; ?>

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

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Уведомления</div>
    </div>
    <div class="card-body">
        <?php if ($smsNotifications === []): ?>
            <p class="text-muted mb-0">Уведомлений ещё не было.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($smsNotifications as $notification): ?>
                    <li class="list-group-item px-0">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold"><?= e($notification['message']) ?></span>
                            <span class="text-muted"><?= e(date('d.m.Y H:i', strtotime((string) $notification['created_at']))) ?></span>
                        </div>
                        <div class="text-muted small">Телефон: <?= e($notification['phone']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php
$warrantyUntil = warrantyExpiresAt($order['delivered_at']);
$underWarranty = isUnderWarranty($order['delivered_at'], date('Y-m-d'));
?>
<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Возврат и гарантия</div>
    </div>
    <div class="card-body">
        <p class="mb-3">
            <strong>Гарантия до:</strong>
            <?php if ($warrantyUntil !== null): ?>
                <?= e(date('d.m.Y', strtotime($warrantyUntil))) ?>
                <span class="badge <?= $underWarranty ? 'bg-success' : 'bg-secondary' ?>"><?= $underWarranty ? 'В пределах гарантии' : 'Гарантия истекла' ?></span>
            <?php else: ?>
                — (Заказ ещё не доставлен)
            <?php endif; ?>
        </p>

        <?php if ($status === ORDER_STATUS_DELIVERED): ?>
            <form method="post" action="/admin/orders/<?= e($orderId) ?>/returns" class="mb-3">
                <?= csrfField() ?>
                <div class="mb-2">
                    <label for="return-note" class="form-label">Комментарий по итогам звонка (необязательно)</label>
                    <textarea id="return-note" name="note" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-outline-danger">Зафиксировать возврат</button>
            </form>
        <?php endif; ?>

        <?php if ($returns === []): ?>
            <p class="text-muted mb-0">Обращений по этому Заказу не было.</p>
        <?php else: ?>
            <h6>Обращения по этому Заказу</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($returns as $return): ?>
                    <li class="list-group-item px-0">
                        <div class="fw-semibold"><?= e(date('d.m.Y H:i', strtotime((string) $return['created_at']))) ?></div>
                        <?php if ($return['note'] !== null && $return['note'] !== ''): ?>
                            <div><?= nl2br(e($return['note'])) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($canCancel): ?>
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="/admin/orders/<?= e($orderId) ?>/cancel">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelOrderModalLabel">Отмена Заказа №<?= e($orderId) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label d-block">Ветка отмены</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="branch" id="branch-standard" value="standard" checked>
                                <label class="form-check-label" for="branch-standard">Стандартная</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="branch" id="branch-non-standard" value="non_standard">
                                <label class="form-check-label" for="branch-non-standard">Нестандартный размер</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="cancel-note" class="form-label">Комментарий (обязателен для нестандартного размера)</label>
                            <textarea id="cancel-note" name="note" class="form-control" rows="3"></textarea>
                        </div>
                        <?php if ($order['payment_status'] !== PAYMENT_STATUS_UNPAID): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="refund_confirmed" id="refund-confirmed" value="1">
                                <label class="form-check-label" for="refund-confirmed">Предоплата возвращена переводом на карту</label>
                            </div>
                        <?php endif; ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="mark_as_sample" id="mark-as-sample" value="1" aria-describedby="mark-as-sample-help">
                            <label class="form-check-label" for="mark-as-sample">Вариант уже изготовлен — оставить как Выставочный образец</label>
                            <div id="mark-as-sample-help" class="form-text">Только для стандартного размера; образцами будут отмечены все Варианты Заказа.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-danger">Отменить заказ</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
