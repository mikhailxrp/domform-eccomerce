<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $preset */
/** @var string $from */
/** @var string $to */
/** @var string|null $error */
/** @var array{orders_count: int|string, orders_total: string}|null $summary */
/** @var array<int, array{day: string, orders_count: int|string, orders_total: string}> $byDay */

include ROOT_PATH . '/src/Views/layout/admin-header.php';

$presetLabels = [
    'today'  => 'Сегодня',
    'week'   => 'Неделя',
    'month'  => 'Месяц',
    'custom' => 'Произвольный период',
];
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Отчёты</h4>
        <p class="mb-0 text-muted">Количество и сумма Заказов за период (без отменённых)</p>
    </div>
</div>

<div class="card custom-card">
    <div class="card-body">
        <form method="get" action="/admin/reports" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <label for="report-preset" class="form-label">Период</label>
                <select id="report-preset" name="preset" class="form-select">
                    <?php foreach ($presetLabels as $presetValue => $presetLabel): ?>
                        <option value="<?= e($presetValue) ?>"<?= $preset === $presetValue ? ' selected' : '' ?>><?= e($presetLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label for="report-from" class="form-label">С (для произвольного периода)</label>
                <input type="date" id="report-from" name="from" class="form-control" value="<?= e($from) ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <label for="report-to" class="form-label">По</label>
                <input type="date" id="report-to" name="to" class="form-control" value="<?= e($to) ?>">
            </div>
            <div class="col-sm-2 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Показать</button>
            </div>
        </form>

        <?php if ($error !== null): ?>
            <div class="alert alert-danger mb-0"><?= e($error) ?></div>
        <?php else: ?>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card custom-card">
                        <div class="card-body text-center">
                            <p class="text-muted mb-1">Заказов за период</p>
                            <h3 class="mb-0"><?= e((string) $summary['orders_count']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card custom-card">
                        <div class="card-body text-center">
                            <p class="text-muted mb-1">Сумма за период</p>
                            <h3 class="mb-0"><?= formatPrice($summary['orders_total']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($byDay === []): ?>
                <p class="text-muted text-center py-5 mb-0">За выбранный период Заказов нет.</p>
            <?php else: ?>
                <div class="mb-4">
                    <canvas
                        id="report-chart"
                        data-labels="<?= e(json_encode(array_column($byDay, 'day'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
                        data-values="<?= e(json_encode(array_map(static fn (array $row): string => $row['orders_total'], $byDay), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
                    ></canvas>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered text-nowrap w-100">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Заказов</th>
                                <th>Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byDay as $row): ?>
                                <tr>
                                    <td><?= e($row['day']) ?></td>
                                    <td><?= e((string) $row['orders_count']) ?></td>
                                    <td><?= formatPrice($row['orders_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($error === null && $byDay !== []): ?>
    <script src="/assets/admin/libs/chart.js/chart.min.js"></script>
<?php endif; ?>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
