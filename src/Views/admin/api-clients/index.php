<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<int, array<string, mixed>> $clients */
/** @var array<string, string> $values */
/** @var array<string, bool> $errors */
/** @var string $appUrl */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($title) ?></h4>
        <p class="text-muted mb-0">Подключение консультанта на сторонний сайт одной строкой кода</p>
    </div>
</div>

<div class="row">
    <div class="col-xl-5">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Новый ключ</div>
            </div>
            <div class="card-body">
                <form method="post" action="/admin/api-clients">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="api-client-name" class="form-label">Название сайта</label>
                        <input type="text" id="api-client-name" name="name" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($values['name'] ?? '') ?>" maxlength="100">
                        <?php if (!empty($errors['name'])): ?>
                            <div class="invalid-feedback">Укажите название (до 100 символов).</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="api-client-origins" class="form-label">Разрешённые домены</label>
                        <textarea id="api-client-origins" name="allowed_origins" rows="4" class="form-control <?= !empty($errors['origins']) ? 'is-invalid' : '' ?>" placeholder="https://example.com"><?= e($values['allowed_origins'] ?? '') ?></textarea>
                        <?php if (!empty($errors['origins'])): ?>
                            <div class="invalid-feedback">Укажите хотя бы один адрес в формате https://example.com.</div>
                        <?php endif; ?>
                        <div class="form-text">
                            По одному на строку, с протоколом и без пути. Поддомены и
                            маски не поддерживаются: <code>https://site.ru</code> и
                            <code>https://www.site.ru</code> — разные домены.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Создать ключ</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Подключённые сайты</div>
            </div>
            <div class="card-body">
                <?php if ($clients === []): ?>
                    <p class="text-muted mb-0">Пока нет ни одного ключа.</p>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="fw-semibold"><?= e((string) $client['name']) ?></div>
                                <span class="badge <?= $client['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $client['is_active'] ? 'Активен' : 'Отключён' ?>
                                </span>
                            </div>
                            <div class="text-muted fs-12 mb-2"><?= nl2br(e((string) $client['allowed_origins'])) ?></div>
                            <label class="form-label fs-12 mb-1" for="api-snippet-<?= e((string) $client['id']) ?>">Код для вставки на сайт</label>
                            <textarea id="api-snippet-<?= e((string) $client['id']) ?>" class="form-control fs-12 mb-2" rows="2" readonly><script src="<?= e($appUrl) ?>/embed/consultant.js" data-key="<?= e((string) $client['api_key']) ?>" async></script></textarea>
                            <form method="post" action="/admin/api-clients/<?= e((string) $client['id']) ?>/toggle">
                                <?= csrfField() ?>
                                <input type="hidden" name="is_active" value="<?= $client['is_active'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm <?= $client['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <?= $client['is_active'] ? 'Отключить' : 'Включить' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
