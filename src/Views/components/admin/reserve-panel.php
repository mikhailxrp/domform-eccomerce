<?php

declare(strict_types=1);

/**
 * Блок «Резерв Выставочного образца» карточки Заказа (Таск 3 Фазы 5,
 * `FR-STOCK-002`). Ожидает `$orderId` (string) и `$reserves` — строки
 * `getOrderReserves()`. Действия (срок, снятие) — только у активного
 * резерва; `released`/`fulfilled` показываются как история.
 */

/** @var string $orderId */
/** @var array<int, array<string, mixed>> $reserves */
?>
<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">Резерв Выставочного образца</div>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Срок согласован с Покупателем устно — система не снимает резерв автоматически. Снятие по истечении срока — вручную, кнопкой ниже.</p>

        <div class="table-responsive">
            <table class="table table-bordered text-nowrap w-100 mb-0">
                <thead>
                    <tr>
                        <th>Вариант</th>
                        <th>Статус</th>
                        <th>Закреплён</th>
                        <th>Срок (устно)</th>
                        <th>Снят / списан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reserves as $reserve): ?>
                        <?php
                        $isActive       = $reserve['status'] === RESERVE_STATUS_ACTIVE;
                        $reserveBaseUrl = '/admin/orders/' . e($orderId) . '/reserves/' . e((string) $reserve['id']);
                        $variantLabel   = $reserve['variant_material'] . ($reserve['variant_color'] !== null ? ', ' . $reserve['variant_color'] : '');
                        ?>
                        <tr>
                            <td>
                                <?= e($reserve['product_name']) ?><br>
                                <small class="text-muted"><?= e($reserve['variant_sku']) ?> · <?= e($variantLabel) ?></small>
                            </td>
                            <td><span class="badge <?= e(reserveStatusBadgeClass($reserve['status'])) ?>"><?= e(reserveStatusLabel($reserve['status'])) ?></span></td>
                            <td><?= e(date('d.m.Y H:i', strtotime((string) $reserve['created_at']))) ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <form method="post" action="<?= $reserveBaseUrl ?>/agreed-until" class="d-flex gap-2 align-items-center">
                                        <?= csrfField() ?>
                                        <label for="agreed-until-<?= e((string) $reserve['id']) ?>" class="visually-hidden">Срок резерва</label>
                                        <input type="date" id="agreed-until-<?= e((string) $reserve['id']) ?>" name="agreed_until" class="form-control form-control-sm" value="<?= e((string) ($reserve['agreed_until'] ?? '')) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Сохранить</button>
                                    </form>
                                <?php else: ?>
                                    <?= $reserve['agreed_until'] !== null ? e(date('d.m.Y', strtotime((string) $reserve['agreed_until']))) : '—' ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $reserve['released_at'] !== null ? e(date('d.m.Y H:i', strtotime((string) $reserve['released_at']))) : '—' ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <form method="post" action="<?= $reserveBaseUrl ?>/release">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Снять резерв</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
