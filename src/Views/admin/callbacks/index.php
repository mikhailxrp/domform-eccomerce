<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $callbacks */
/** @var string $statusFilter */
/** @var array $pagination */
/** @var array<int, string> $paginationLinks */
/** @var string|null $prevUrl */
/** @var string|null $nextUrl */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Заявки на звонок</h4>
        <p class="mb-0 text-muted">Заявки «Перезвоните мне» со страницы «Контакты» (`FR-CNT-001`)</p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <form method="get" action="/admin/callbacks" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <label for="callback-status-filter" class="form-label">Статус</label>
                <select id="callback-status-filter" name="status" class="form-select">
                    <option value=""<?= $statusFilter === '' ? ' selected' : '' ?>>Все статусы</option>
                    <option value="<?= e(CALLBACK_STATUS_NEW) ?>"<?= $statusFilter === CALLBACK_STATUS_NEW ? ' selected' : '' ?>>Новые</option>
                    <option value="<?= e(CALLBACK_STATUS_PROCESSED) ?>"<?= $statusFilter === CALLBACK_STATUS_PROCESSED ? ' selected' : '' ?>>Обработанные</option>
                </select>
            </div>
            <div class="col-sm-2 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Показать</button>
            </div>
        </form>

        <?php if ($callbacks === []): ?>
            <p class="text-muted text-center py-5 mb-0">Заявок не найдено.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>Имя</th>
                            <th>Телефон</th>
                            <th>Комментарий</th>
                            <th>Дата</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($callbacks as $callback): ?>
                            <?php $isProcessed = $callback['status'] === CALLBACK_STATUS_PROCESSED; ?>
                            <tr>
                                <td><?= e($callback['name']) ?></td>
                                <td><a href="tel:<?= e((string) $callback['phone']) ?>"><?= e((string) $callback['phone']) ?></a></td>
                                <td class="text-wrap"><?= $callback['comment'] !== null ? nl2br(e((string) $callback['comment'])) : '—' ?></td>
                                <td><?= e(date('d.m.Y H:i', strtotime((string) $callback['created_at']))) ?></td>
                                <td>
                                    <span class="badge <?= $isProcessed ? 'bg-success' : 'bg-warning' ?>">
                                        <?= $isProcessed ? 'Обработана' : 'Новая' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isProcessed): ?>
                                        &mdash;
                                    <?php else: ?>
                                        <form method="post" action="/admin/callbacks/<?= e((string) $callback['id']) ?>/process">
                                            <?= csrfField() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success">Обработано</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
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
