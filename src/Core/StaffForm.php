<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Validation.php';

/**
 * Чистая валидация формы сотрудника (`users`, `role IN ('manager',
 * 'admin')`, `FR-ADM-007` п. 1, Таск 8 Фазы 8) — без обращения к БД,
 * по образцу `Core/Account.php`.
 */

const STAFF_ROLES = ['manager', 'admin'];

/**
 * Телефон необязателен (`users.phone NULL` для `manager`/`admin`,
 * `database.md`) — ошибка только если указан и не проходит
 * `validatePhone()`. Пароль — то же правило `validatePassword()`
 * (≥ 8 символов), что при регистрации Покупателя, без доп. сложности
 * (`php.md`).
 */
function validateStaffInput(array $input): array
{
    $name     = (string) ($input['name'] ?? '');
    $email    = (string) ($input['email'] ?? '');
    $phone    = (string) ($input['phone'] ?? '');
    $password = (string) ($input['password'] ?? '');
    $role     = (string) ($input['role'] ?? '');

    return [
        'name'     => trim($name) === '',
        'email'    => !validateEmail($email),
        'phone'    => $phone !== '' && !validatePhone($phone),
        'password' => !validatePassword($password),
        'role'     => !in_array($role, STAFF_ROLES, true),
    ];
}
