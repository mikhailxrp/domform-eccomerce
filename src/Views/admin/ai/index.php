<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $month */
/** @var string $spend */
/** @var bool $limitExceeded */
/** @var array{total_spend: string, total_requests: int, total_errors: int, by_assistant: array<string, array{spend: string, requests_count: int, errors_count: int}>, by_class: array<string, array{spend: string, requests_count: int}>} $summary */
/** @var array<string, string> $values */
/** @var array<string, bool> $errors */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$assistantLabels = [
    'specs'       => 'Разбор характеристик',
    'description' => 'Генератор описания',
    'consultant'  => 'Консультант в чате (включая подбор товара и инфо о заказе)',
];

$classLabels = [
    'anonymous'  => 'Обезличенные данные (YandexGPT)',
    'user_input' => 'Пользовательский ввод (YandexGPT)',
];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0"><?= e($title) ?></h4>
        <p class="text-muted mb-0">Расход за <?= e($month) ?>, месячный лимит и включение помощников</p>
    </div>
    <a href="/admin/ai/specs" class="btn btn-outline-primary">Очередь разбора характеристик →</a>
</div>

<div class="row">
    <div class="col-xl-7">
        <div class="card custom-card mb-3">
            <div class="card-header">
                <div class="card-title">Расход за <?= e($month) ?></div>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-baseline gap-2 mb-3">
                    <span class="fs-24 fw-semibold"><?= e(formatPrice($spend)) ?></span>
                    <span class="text-muted">из <?= e(formatPrice($values['ai_monthly_limit_rub'])) ?> лимита</span>
                    <?php if ($limitExceeded): ?>
                        <span class="badge bg-warning-transparent">Лимит превышен</span>
                    <?php endif; ?>
                </div>

                <table class="table table-sm mb-3">
                    <caption class="visually-hidden">Расход по помощникам</caption>
                    <thead>
                        <tr>
                            <th scope="col">Помощник</th>
                            <th scope="col" class="text-end">Запросов</th>
                            <th scope="col" class="text-end">Ошибок</th>
                            <th scope="col" class="text-end">Расход</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assistantLabels as $key => $label): ?>
                            <?php $row = $summary['by_assistant'][$key] ?? ['spend' => '0.00', 'requests_count' => 0, 'errors_count' => 0]; ?>
                            <tr>
                                <td><?= e($label) ?></td>
                                <td class="text-end"><?= e((string) $row['requests_count']) ?></td>
                                <td class="text-end"><?= e((string) $row['errors_count']) ?></td>
                                <td class="text-end"><?= e(formatPrice($row['spend'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <table class="table table-sm mb-0">
                    <caption class="visually-hidden">Расход по классам задач</caption>
                    <thead>
                        <tr>
                            <th scope="col">Класс задачи / провайдер</th>
                            <th scope="col" class="text-end">Запросов</th>
                            <th scope="col" class="text-end">Расход</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classLabels as $key => $label): ?>
                            <?php $row = $summary['by_class'][$key] ?? ['spend' => '0.00', 'requests_count' => 0]; ?>
                            <tr>
                                <td><?= e($label) ?></td>
                                <td class="text-end"><?= e((string) $row['requests_count']) ?></td>
                                <td class="text-end"><?= e(formatPrice($row['spend'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">Настройки ИИ</div>
            </div>
            <div class="card-body">
                <form method="post" action="/admin/ai">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="ai-monthly-limit" class="form-label">Месячный лимит расхода, ₽</label>
                        <input type="text" id="ai-monthly-limit" name="ai_monthly_limit_rub" class="form-control <?= !empty($errors['ai_monthly_limit_rub']) ? 'is-invalid' : '' ?>" value="<?= e($values['ai_monthly_limit_rub']) ?>">
                        <?php if (!empty($errors['ai_monthly_limit_rub'])): ?>
                            <div class="invalid-feedback">Число больше нуля, например 5000.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="ai-usd-rate" class="form-label">Курс USD → RUB (для OpenRouter)</label>
                        <input type="text" id="ai-usd-rate" name="ai_usd_rate" class="form-control <?= !empty($errors['ai_usd_rate']) ? 'is-invalid' : '' ?>" value="<?= e($values['ai_usd_rate']) ?>">
                        <?php if (!empty($errors['ai_usd_rate'])): ?>
                            <div class="invalid-feedback">Число больше нуля, например 95.00.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="ai-yandex-price" class="form-label">Цена YandexGPT за 1000 токенов, ₽</label>
                        <input type="text" id="ai-yandex-price" name="ai_yandex_price_per_1k" class="form-control <?= !empty($errors['ai_yandex_price_per_1k']) ? 'is-invalid' : '' ?>" value="<?= e($values['ai_yandex_price_per_1k']) ?>">
                        <?php if (!empty($errors['ai_yandex_price_per_1k'])): ?>
                            <div class="invalid-feedback">Число больше нуля, например 1.20.</div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="ai_specs_enabled" value="1" id="ai-specs-enabled" <?= $values['ai_specs_enabled'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ai-specs-enabled">Разбор характеристик</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="ai_description_enabled" value="1" id="ai-description-enabled" <?= $values['ai_description_enabled'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ai-description-enabled">Генератор описания</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="ai_consultant_enabled" value="1" id="ai-consultant-enabled" <?= $values['ai_consultant_enabled'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ai-consultant-enabled">Консультант в чате (включая подбор товара и инфо о заказе)</label>
                    </div>

                    <div class="form-text mb-3">
                        Выключение помощника не влияет на остальных — оформление Заказа и каталог всегда доступны, даже при выключенном ИИ (<code>AC-06</code>).
                    </div>

                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
