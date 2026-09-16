<?php

declare(strict_types=1);

$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
?>
<?php if ($flashSuccess !== null): ?>
    <div class="container">
        <div class="alert alert-success mt-4" role="alert"><?= e($flashSuccess) ?></div>
    </div>
<?php endif; ?>
<?php if ($flashError !== null): ?>
    <div class="container">
        <div class="alert alert-danger mt-4" role="alert"><?= e($flashError) ?></div>
    </div>
<?php endif; ?>
