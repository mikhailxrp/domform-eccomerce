<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';

/**
 * Личные данные и смена пароля (`FR-ACC-004`, `.docs/phases/
 * phase-7.md`, Таск 3) — чистая нормализация/валидация без обращения
 * к БД/сессии, по образцу `Core/Checkout.php`. Проверка текущего
 * пароля (`password_verify()`) и занятости email другим пользователем
 * (`isEmailTakenByOther()`) требуют БД — это `AccountController`, не
 * здесь.
 */

/**
 * `FR-ACC-004` правило 2 — смена email требует текущий пароль, имя
 * меняется без него. `$currentEmail` сравнивается без учёта регистра —
 * email хранится в нижнем регистре (`AuthController::register()`).
 */
function validateProfileInput(array $input, string $currentEmail): array
{
    $name  = trim((string) ($input['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($input['email'] ?? '')), 'UTF-8');

    $emailChanged = $email !== mb_strtolower($currentEmail, 'UTF-8');

    return [
        'name'             => mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 100,
        'email'            => !validateEmail($email),
        'current_password' => $emailChanged && trim((string) ($input['current_password'] ?? '')) === '',
    ];
}

/**
 * Новый пароль отдельно проверяется на совпадение с текущим (введённым
 * в этой же форме, не хэшем из БД) — бессмысленно «менять» пароль на
 * тот же самый.
 */
function validatePasswordChangeInput(array $input): array
{
    $currentPassword = (string) ($input['current_password'] ?? '');
    $newPassword     = (string) ($input['new_password'] ?? '');
    $confirmPassword = (string) ($input['new_password_confirm'] ?? '');

    return [
        'current_password'     => $currentPassword === '',
        'new_password'         => !validatePassword($newPassword) || $newPassword === $currentPassword,
        'new_password_confirm' => $confirmPassword !== $newPassword,
    ];
}
