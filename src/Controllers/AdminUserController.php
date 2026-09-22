<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/RememberToken.php';
require_once ROOT_PATH . '/src/Core/StaffForm.php';
require_once ROOT_PATH . '/src/Core/Validation.php';

/**
 * `/admin/users` (`FR-ADM-007` п. 1–3, Таск 8 Фазы 8) — `admin`-only,
 * первый после `/admin/settings` маршрут без `manager` (`Q-DEV-001`).
 * Список и форма создания — на одной странице, по образцу
 * `AdminReviewController::index()`/`storeShopReview()`.
 */
class AdminUserController
{
    public function index(array $old = [], array $errors = []): void
    {
        requireRole(['admin']);

        render('admin/users/index', [
            'title'  => 'Сотрудники',
            'staff'  => getStaffUsers(),
            'old'    => $old,
            'errors' => $errors,
        ]);
    }

    /**
     * Email занят другим аккаунтом проверяется явно (сообщение сразу
     * под полем), плюс `createStaffUser()` перехватывает гонку на
     * `UNIQUE(email)` как страховку — тот же двойной приём, что
     * `AuthController::register()`, но без общего "AUTH_ERROR": здесь
     * это внутренняя форма Администратора, не публичная регистрация,
     * скрывать существование email незачем.
     */
    public function store(): void
    {
        requireRole(['admin']);
        requireCsrf();

        $input  = $this->staffInputFromRequest();
        $errors = validateStaffInput($input);

        if (!$errors['email'] && findUserByEmail($input['email']) !== null) {
            $errors['email'] = true;
        }

        if (in_array(true, $errors, true)) {
            $this->index($input, $errors);
            return;
        }

        $userId = createStaffUser([
            'name'          => $input['name'],
            'email'         => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'phone'         => $input['phone'] !== '' ? normalizePhone($input['phone']) : null,
            'role'          => $input['role'],
        ]);

        if ($userId === null) {
            $errors['email'] = true;
            $this->index($input, $errors);
            return;
        }

        setFlash('success', 'Сотрудник создан.');
        redirect('/admin/users');
    }

    public function block(string $id): void
    {
        $this->setBlocked((int) $id, true, 'Сотрудник заблокирован.');
    }

    public function unblock(string $id): void
    {
        $this->setBlocked((int) $id, false, 'Сотрудник разблокирован.');
    }

    /**
     * Себя заблокировать нельзя (`FR-ADM-007` правило 3) — проверка до
     * обращения к Model, чтобы не отличать «отказ» от «не найден».
     * При блокировке remember-cookie не должна восстановить сессию —
     * `deleteRememberTokens()`, как при смене пароля
     * (`AuthController::reset()`).
     */
    private function setBlocked(int $id, bool $blocked, string $successMessage): void
    {
        requireRole(['admin']);
        requireCsrf();

        if ($blocked && $id === currentUser()['id']) {
            setFlash('error', 'Нельзя заблокировать собственную учётную запись.');
            redirect('/admin/users');
        }

        if (!setUserBlocked($id, $blocked)) {
            setFlash('error', 'Сотрудник не найден.');
            redirect('/admin/users');
        }

        if ($blocked) {
            deleteRememberTokens($id);
        }

        setFlash('success', $successMessage);
        redirect('/admin/users');
    }

    private function staffInputFromRequest(): array
    {
        return [
            'name'     => trim((string) input('name')),
            'email'    => mb_strtolower(trim((string) input('email')), 'UTF-8'),
            'phone'    => trim((string) input('phone')),
            'password' => (string) input('password'),
            'role'     => (string) input('role'),
        ];
    }
}
