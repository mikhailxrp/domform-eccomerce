<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Core/Validation.php';

class AuthController
{
    /**
     * Один и тот же текст для «email занят» (регистрация) и «неверные
     * данные» (вход) — иначе перебором узнают, кто зарегистрирован.
     */
    private const AUTH_ERROR = 'Неверный email или пароль.';

    public function showLogin(): void
    {
        redirectIfAuthenticated();
        render('auth/login', [
            'title'  => 'Вход',
            'old'    => [],
            'errors' => [],
        ]);
    }

    public function login(): void
    {
        requireCsrf();

        if (tooManyAttempts('login', 5, 60)) {
            setFlash('error', 'Слишком много попыток входа. Попробуйте через минуту.');
            redirect('/login');
        }

        $email    = mb_strtolower(trim((string) input('email')), 'UTF-8');
        $password = (string) input('password');

        $user = $email !== '' ? findUserByEmail($email) : null;

        $isValid = $user !== null
            && (int) $user['is_blocked'] === 0
            && password_verify($password, $user['password_hash']);

        if (!$isValid) {
            hitRateLimit('login');
            logWarning('Неудачная попытка входа', ['email' => $email]);
            setFlash('error', self::AUTH_ERROR);
            render('auth/login', [
                'title'  => 'Вход',
                'old'    => ['email' => $email],
                'errors' => ['email' => true, 'password' => true],
            ]);
            return;
        }

        clearRateLimit('login');
        regenerateSession();
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];

        setFlash('success', 'Вы вошли в аккаунт.');
        redirect('/');
    }

    public function showRegister(): void
    {
        redirectIfAuthenticated();
        render('auth/register', [
            'title'  => 'Регистрация',
            'old'    => [],
            'errors' => [],
        ]);
    }

    public function register(): void
    {
        requireCsrf();

        $name     = trim((string) input('name'));
        $email    = mb_strtolower(trim((string) input('email')), 'UTF-8');
        $phone    = trim((string) input('phone'));
        $password = (string) input('password');

        $errors = [
            'name'     => $name === '',
            'email'    => !validateEmail($email),
            'phone'    => !validatePhone($phone),
            'password' => !validatePassword($password),
        ];

        $old = ['name' => $name, 'email' => $email, 'phone' => $phone];

        if (in_array(true, $errors, true)) {
            render('auth/register', [
                'title'  => 'Регистрация',
                'old'    => $old,
                'errors' => $errors,
            ]);
            return;
        }

        if (findUserByEmail($email) !== null) {
            setFlash('error', self::AUTH_ERROR);
            render('auth/register', [
                'title'  => 'Регистрация',
                'old'    => $old,
                'errors' => [],
            ]);
            return;
        }

        $userId = createUser($name, $email, password_hash($password, PASSWORD_DEFAULT), normalizePhone($phone));

        if ($userId === null) {
            setFlash('error', self::AUTH_ERROR);
            render('auth/register', [
                'title'  => 'Регистрация',
                'old'    => $old,
                'errors' => [],
            ]);
            return;
        }

        setFlash('success', 'Регистрация прошла успешно. Теперь войдите в аккаунт.');
        redirect('/login');
    }

    public function logout(): void
    {
        requireCsrf();

        unset($_SESSION['user_id'], $_SESSION['user_name']);
        regenerateSession();

        setFlash('success', 'Вы вышли из аккаунта.');
        redirect('/');
    }
}
