<?php

declare(strict_types=1);

/** @var string $title */
/** @var array<string, int> $statusCounts */

include ROOT_PATH . '/src/Views/layout/admin-header.php';
?>

<div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
    <div>
        <h4 class="mb-0">Панель управления</h4>
        <p class="mb-0 text-muted">Заказы по статусам</p>
    </div>
</div>

<div class="row">
    <?php foreach (ORDER_STATUS_LABELS as $status => $label): ?>
        <?php
        $badgeClass    = orderStatusBadgeClass($status);
        $gradientClass = explode(' ', $badgeClass)[0] . '-gradient';
        $count         = $statusCounts[$status] ?? 0;
        ?>
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <a href="/admin/orders?status=<?= e($status) ?>" class="text-decoration-none">
                <div class="card overflow-hidden sales-card <?= e($gradientClass) ?>">
                    <div class="px-3 pt-3 pb-3">
                        <h6 class="mb-3 fs-12 text-fixed-white text-uppercase"><?= e($label) ?></h6>
                        <h4 class="fs-24 fw-bold mb-0 text-fixed-white"><?= e((string) $count) ?></h4>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php include ROOT_PATH . '/src/Views/layout/admin-footer.php'; ?>
