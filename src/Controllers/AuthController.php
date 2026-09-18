<?php

declare(strict_types=1);

namespace App\Controllers;

require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/RememberToken.php';
require_once ROOT_PATH . '/src/Models/PasswordReset.php';
require_once ROOT_PATH . '/src/Models/Cart.php';
require_once ROOT_PATH . '/src/Models/Order.php';
require_once ROOT_PATH . '/src/Services/Mailer.php';
require_once ROOT_PATH . '/src/Core/Validation.php';

class AuthController
{
    /**
     * Один и тот же текст для «email занят» (регистрация) и «неверные
     * данные» (вход) — иначе перебором узнают, кто зарегистрирован.
     */
    private const AUTH_ERROR = 'Неверный email или пароль.';

    /**
     * Один и тот же ответ для любого email — тем же принципом, что
     * AUTH_ERROR: существование аккаунта не раскрывается.
     */
    private const FORGOT_GENERIC_MESSAGE = 'Если такой email зарегистрирован, мы отправили на него ссылку для восстановления пароля.';

    private const RESET_LINK_INVALID_MESSAGE = 'Ссылка недействительна или устарела.';

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
        $_SESSION['user_role'] = $user['role'];

        if (isset($_COOKIE['cart_token'])) {
            mergeGuestCart($_COOKIE['cart_token'], (int) $user['id']);
        }
        refreshCartCount(cartOwner());

        if (input('remember') === '1') {
            $token = createRememberToken((int) $user['id']);
            setRememberCookie(
                $token['selector'],
                $token['validator'],
                time() + REMEMBER_TOKEN_TTL_DAYS * 24 * 60 * 60
            );
        }

        setFlash('success', 'Вы вошли в аккаунт.');
        redirect(homeUrlForRole($user['role']));
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

        if (isset($_COOKIE['cart_token'])) {
            mergeGuestCart($_COOKIE['cart_token'], $userId);
            refreshCartCount(cartOwner());
        }

        linkGuestOrdersToUser($email, $userId);

        setFlash('success', 'Регистрация прошла успешно. Теперь войдите в аккаунт.');
        redirect('/login');
    }

    public function logout(): void
    {
        requireCsrf();

        $user = currentUser();
        if ($user !== null) {
            deleteRememberTokens($user['id']);
        }
        clearRememberCookie();

        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['cart_count']);
        regenerateSession();

        setFlash('success', 'Вы вышли из аккаунта.');
        redirect('/');
    }

    public function showForgot(): void
    {
        redirectIfAuthenticated();
        render('auth/forgot', [
            'title'  => 'Восстановление пароля',
            'old'    => [],
            'errors' => [],
        ]);
    }

    public function forgot(): void
    {
        requireCsrf();

        if (tooManyAttempts('forgot', 3, 60)) {
            setFlash('error', 'Слишком много попыток. Попробуйте через минуту.');
            redirect('/forgot-password');
        }

        $email = mb_strtolower(trim((string) input('email')), 'UTF-8');

        if (!validateEmail($email)) {
            render('auth/forgot', [
                'title'  => 'Восстановление пароля',
                'old'    => ['email' => $email],
                'errors' => ['email' => true],
            ]);
            return;
        }

        hitRateLimit('forgot');

        $user = findUserByEmail($email);
        if ($user !== null) {
            $token    = createPasswordReset((int) $user['id']);
            $resetUrl = rtrim(APP_URL, '/') . '/reset-password/' . $token;
            $body     = renderEmailBody('password-reset', [
                'userName' => $user['name'],
                'resetUrl' => $resetUrl,
            ]);
            sendMail($user['email'], 'Восстановление пароля — ДомФорм', $body);
        }

        setFlash('success', self::FORGOT_GENERIC_MESSAGE);
        redirect('/forgot-password');
    }

    public function showReset(string $token): void
    {
        if (findValidPasswordReset($token) === null) {
            setFlash('error', self::RESET_LINK_INVALID_MESSAGE);
            redirect('/forgot-password');
        }

        render('auth/reset', [
            'title'  => 'Новый пароль',
            'token'  => $token,
            'errors' => [],
        ]);
    }

    public function reset(string $token): void
    {
        requireCsrf();

        $reset = findValidPasswordReset($token);
        if ($reset === null) {
            setFlash('error', self::RESET_LINK_INVALID_MESSAGE);
            redirect('/forgot-password');
        }

        $password = (string) input('password');

        if (!validatePassword($password)) {
            render('auth/reset', [
                'title'  => 'Новый пароль',
                'token'  => $token,
                'errors' => ['password' => true],
            ]);
            return;
        }

        updateUserPasswordHash((int) $reset['user_id'], password_hash($password, PASSWORD_DEFAULT));
        markPasswordResetUsed((int) $reset['id']);
        deleteRememberTokens((int) $reset['user_id']);

        setFlash('success', 'Пароль изменён. Войдите с новым паролем.');
        redirect('/login');
    }
}
