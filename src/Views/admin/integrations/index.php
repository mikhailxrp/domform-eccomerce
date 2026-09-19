<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, array<int, array<string, mixed>>> $groupedIntegrations */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$categoryLabels = [
    'crm'        => 'CRM-системы',
    'accounting' => 'Системы учёта',
    'telephony'  => 'Телефония',
    'marketing'  => 'Рассылки и уведомления',
];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Интеграции</h4>
        <p class="text-muted mb-0">Подключение внешних систем к магазину</p>
    </div>
</div>

<div class="alert alert-info">
    Это демонстрационная страница: здесь будет подключение нужных вам
    систем — CRM, учёт товаров и остатков (от 1С до МойСклад), IP-телефония,
    SMS- и email-рассылки и другие сервисы. Переключатели показывают
    возможность подключения, реального обмена данными с перечисленными
    сервисами эта страница не выполняет.
</div>

<form method="post" action="/admin/integrations">
    <?= csrfField() ?>

    <?php foreach ($groupedIntegrations as $category => $integrations): ?>
        <div class="card custom-card mb-3">
            <div class="card-header">
                <div class="card-title"><?= e($categoryLabels[$category] ?? $category) ?></div>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($integrations as $integration): ?>
                        <li class="list-group-item d-flex align-items-start gap-3">
                            <i class="<?= e((string) $integration['icon']) ?> fs-20 text-primary mt-1"></i>
                            <div class="flex-fill">
                                <div class="fw-semibold"><?= e((string) $integration['name']) ?></div>
                                <div class="text-muted fs-12"><?= e((string) $integration['description']) ?></div>
                            </div>
                            <div class="form-check form-switch mt-1">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    name="integrations[]"
                                    value="<?= e((string) $integration['code']) ?>"
                                    id="integration-<?= e((string) $integration['code']) ?>"
                                    <?= $integration['is_enabled'] ? 'checked' : '' ?>
                                >
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary mb-4">Сохранить</button>
</form>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
