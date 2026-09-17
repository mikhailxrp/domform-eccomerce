<?php

declare(strict_types=1);

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
?>
<?php if ($flashSuccess !== null || $flashError !== null): ?>
    <div class="page-content-offset">
        <?php if ($flashSuccess !== null): ?>
            <div class="container">
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <?= e($flashSuccess) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($flashError !== null): ?>
            <div class="container">
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <?= e($flashError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
