<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, string> $values */
/** @var array<string, bool> $errors */
/** @var array<string, mixed> $systemInfo */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($title) ?></h4>
        <p class="text-muted mb-0">Реквизиты магазина и сведения о системе</p>
    </div>
</div>

<div class="row">
    <div class="col-xl-7">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Реквизиты магазина</div>
            </div>
            <div class="card-body">
                <form method="post" action="/admin/settings">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="setting-shop-phone" class="form-label">Телефон</label>
                        <input type="text" id="setting-shop-phone" name="shop_phone" class="form-control <?= !empty($errors['shop_phone']) ? 'is-invalid' : '' ?>" value="<?= e($values['shop_phone'] ?? '') ?>" placeholder="+7 900 000-00-00">
                        <?php if (!empty($errors['shop_phone'])): ?>
                            <div class="invalid-feedback">Введите телефон в формате +7 900 000-00-00.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="setting-shop-whatsapp" class="form-label">WhatsApp</label>
                        <input type="text" id="setting-shop-whatsapp" name="shop_whatsapp_url" class="form-control <?= !empty($errors['shop_whatsapp_url']) ? 'is-invalid' : '' ?>" value="<?= e($values['shop_whatsapp_url'] ?? '') ?>" placeholder="https://wa.me/79000000000">
                        <?php if (!empty($errors['shop_whatsapp_url'])): ?>
                            <div class="invalid-feedback">Укажите ссылку, начинающуюся с https://.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="setting-shop-email" class="form-label">Email</label>
                        <input type="text" id="setting-shop-email" name="shop_email" class="form-control <?= !empty($errors['shop_email']) ? 'is-invalid' : '' ?>" value="<?= e($values['shop_email'] ?? '') ?>">
                        <?php if (!empty($errors['shop_email'])): ?>
                            <div class="invalid-feedback">Введите корректный email.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="setting-workshop-address" class="form-label">Адрес цеха</label>
                        <input type="text" id="setting-workshop-address" name="workshop_address" class="form-control <?= !empty($errors['workshop_address']) ? 'is-invalid' : '' ?>" value="<?= e($values['workshop_address'] ?? '') ?>">
                        <?php if (!empty($errors['workshop_address'])): ?>
                            <div class="invalid-feedback">Укажите адрес (до 255 символов).</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="setting-work-hours" class="form-label">Режим работы</label>
                        <input type="text" id="setting-work-hours" name="work_hours" class="form-control <?= !empty($errors['work_hours']) ? 'is-invalid' : '' ?>" value="<?= e($values['work_hours'] ?? '') ?>" placeholder="Пн–Сб, 9:00–19:00">
                        <?php if (!empty($errors['work_hours'])): ?>
                            <div class="invalid-feedback">Укажите режим работы (до 100 символов).</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="setting-map-embed-url" class="form-label">Ссылка на карту (iframe Яндекс.Карт)</label>
                        <input type="text" id="setting-map-embed-url" name="map_embed_url" class="form-control <?= !empty($errors['map_embed_url']) ? 'is-invalid' : '' ?>" value="<?= e($values['map_embed_url'] ?? '') ?>" placeholder="https://yandex.ru/map-widget/v1/...">
                        <?php if (!empty($errors['map_embed_url'])): ?>
                            <div class="invalid-feedback">Нужна ссылка на виджет Конструктора карт (содержит map-widget), не обычная ссылка «поделиться местом» — она не встраивается в iframe.</div>
                        <?php endif; ?>
                        <div class="form-text">
                            Пусто — карта на странице «Шоурум» не показывается. Как получить
                            ссылку: <a href="https://yandex.ru/map-constructor/" target="_blank" rel="noopener">Конструктор карт</a>
                            → добавьте метку → «Готовый виджет» → скопируйте адрес из
                            <code>src</code> готового кода (начинается с
                            <code>https://yandex.ru/map-widget/v1/...</code>).
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">О системе</div>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Окружение</span>
                        <span class="fw-semibold"><?= e($systemInfo['app_env']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Версия PHP</span>
                        <span class="fw-semibold"><?= e($systemInfo['php_version']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Версия MySQL</span>
                        <span class="fw-semibold"><?= e($systemInfo['mysql_version']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Размер лога приложения</span>
                        <span class="fw-semibold"><?= e(number_format((int) ($systemInfo['log_size'] / 1024), 0, ',', ' ')) ?> КБ</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Запись в public/uploads</span>
                        <span class="fw-semibold"><?= $systemInfo['uploads_writable'] ? 'Доступна' : 'Недоступна' ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
