<?php

declare(strict_types=1);

/** @var string $status */
?>
<span class="badge <?= e(orderStatusBadgeClass($status)) ?>"><?= e(orderStatusLabel($status)) ?></span>
