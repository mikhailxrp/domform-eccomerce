<?php

declare(strict_types=1);

/** @var string $fieldName */
/** @var string $fieldPlaceholder */
/** @var bool $fieldHasError */
/** @var string $fieldErrorMessage */

?>
<div class="single-form single-form--password">
    <input
        type="password"
        name="<?= e($fieldName) ?>"
        placeholder="<?= e($fieldPlaceholder) ?>"
        class="<?= $fieldHasError ? 'is-invalid' : '' ?>"
        required
    >
    <button type="button" class="single-form__password-toggle" aria-label="Показать пароль">
        <i class="fa fa-eye"></i>
    </button>
    <?php if ($fieldHasError): ?>
        <div class="invalid-feedback"><?= e($fieldErrorMessage) ?></div>
    <?php endif; ?>
</div>
