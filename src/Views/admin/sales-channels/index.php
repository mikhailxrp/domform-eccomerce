<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $channels */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

/**
 * Демо-переписка «единого чата» — статичный макет, не данные из БД: сама
 * задача этой вкладки показать идею («сообщения всех каналов — в одном
 * месте»), а не построить рабочий мессенджер.
 */
$demoThreads = [
    ['name' => 'Ирина, Краснодар', 'channel' => 'WhatsApp', 'icon' => 'bx bxl-whatsapp', 'preview' => 'Здравствуйте! Диван «Модерн» есть в ткани рогожка?', 'time' => '10:24', 'active' => true],
    ['name' => 'Авито · объявление №48213', 'channel' => 'Авито', 'icon' => 'bx bx-store-alt', 'preview' => 'Подскажите срок изготовления кухонного уголка', 'time' => '09:57', 'active' => false],
    ['name' => 'Сергей Петров', 'channel' => 'Telegram', 'icon' => 'bx bxl-telegram', 'preview' => 'Оплатил предоплату, жду подтверждения', 'time' => 'вчера', 'active' => false],
    ['name' => 'Заказ №1042 (сайт)', 'channel' => 'Сайт', 'icon' => 'bx bx-globe', 'preview' => 'Комментарий покупателя к заказу', 'time' => 'вчера', 'active' => false],
];

$demoMessages = [
    ['from' => 'client', 'text' => 'Здравствуйте! Диван «Модерн» есть в ткани рогожка?', 'time' => '10:21'],
    ['from' => 'manager', 'text' => 'Добрый день! Да, рогожка доступна, несколько цветов на выбор.', 'time' => '10:23'],
    ['from' => 'client', 'text' => 'Отлично, а срок изготовления?', 'time' => '10:24'],
];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Каналы продаж</h4>
        <p class="text-muted mb-0">Приём заявок из разных источников в одном месте</p>
    </div>
</div>

<div class="alert alert-info">
    Это демонстрационная версия: переключатели показывают, как магазин
    может собирать заявки из разных каналов (WhatsApp, Авито, Telegram,
    MAX, сам сайт) в одном списке, если у вас нет собственной CRM — либо
    передавать уже созданные заказы в вашу CRM, если она есть (вкладка
    «Интеграции»). Реального подключения к мессенджерам и площадкам эта
    страница не выполняет.
</div>

<div class="row">
    <div class="col-xl-5">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Подключённые каналы</div>
            </div>
            <div class="card-body">
                <form method="post" action="/admin/sales-channels">
                    <?= csrfField() ?>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($channels as $channel): ?>
                            <li class="list-group-item d-flex align-items-start gap-3">
                                <i class="<?= e((string) $channel['icon']) ?> fs-20 text-primary mt-1"></i>
                                <div class="flex-fill">
                                    <div class="fw-semibold"><?= e((string) $channel['name']) ?></div>
                                    <div class="text-muted fs-12"><?= e((string) $channel['description']) ?></div>
                                    <?php if ($channel['is_locked']): ?>
                                        <span class="badge bg-light text-dark mt-1">Основной канал</span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-check form-switch mt-1">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        name="channels[]"
                                        value="<?= e((string) $channel['code']) ?>"
                                        id="channel-<?= e((string) $channel['code']) ?>"
                                        <?= $channel['is_enabled'] ? 'checked' : '' ?>
                                        <?= $channel['is_locked'] ? 'disabled' : '' ?>
                                    >
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title mb-0">Единый чат по всем каналам</div>
                <span class="badge bg-light text-dark">Демо-данные</span>
            </div>
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-md-5 border-end">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($demoThreads as $thread): ?>
                                <li class="list-group-item<?= $thread['active'] ? ' active' : '' ?>">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="<?= e((string) $thread['icon']) ?> fs-16"></i>
                                        <div class="flex-fill overflow-hidden">
                                            <div class="fw-semibold text-truncate"><?= e((string) $thread['name']) ?></div>
                                            <div class="fs-11 text-truncate<?= $thread['active'] ? '' : ' text-muted' ?>"><?= e((string) $thread['preview']) ?></div>
                                        </div>
                                        <div class="fs-11<?= $thread['active'] ? '' : ' text-muted' ?>"><?= e((string) $thread['time']) ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="col-md-7 d-flex flex-column p-3" style="min-height: 320px;">
                        <div class="flex-fill">
                            <?php foreach ($demoMessages as $message): ?>
                                <?php $isManager = $message['from'] === 'manager'; ?>
                                <div class="d-flex mb-2 <?= $isManager ? 'justify-content-end' : 'justify-content-start' ?>">
                                    <div class="p-2 px-3 rounded-3 <?= $isManager ? 'bg-primary text-white' : 'bg-light' ?>" style="max-width: 80%;">
                                        <div class="fs-13"><?= e((string) $message['text']) ?></div>
                                        <div class="fs-10 <?= $isManager ? 'text-white-50' : 'text-muted' ?> text-end mt-1"><?= e((string) $message['time']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="input-group mt-2">
                            <input type="text" class="form-control" placeholder="Сообщение…" disabled>
                            <button class="btn btn-outline-secondary" type="button" disabled>Отправить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
