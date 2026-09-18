<?php

declare(strict_types=1);

/**
 * Общие хелперы приложения.
 */

function loadEnv(string $path): void
{
    if (!is_readable($path)) {
        throw new RuntimeException("Файл окружения не найден: {$path}");
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim(trim($value), '"\'');
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

function env(string $key, ?string $default = null): string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Отсутствует переменная окружения: {$key}");
    }
    return $value;
}

function ensureSessionStarted(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => defined('APP_ENV') && APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function regenerateSession(): void
{
    ensureSessionStarted();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function isAuthenticated(): bool
{
    ensureSessionStarted();
    return normalizeUserId($_SESSION['user_id'] ?? null) !== null;
}

function requireAuth(): void
{
    if (!isAuthenticated()) {
        redirect('/login');
    }
}

function redirectIfAuthenticated(): void
{
    $user = currentUser();
    if ($user !== null) {
        redirect(homeUrlForRole($user['role']));
    }
}

function setFlash(string $key, string $message): void
{
    ensureSessionStarted();
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string
{
    ensureSessionStarted();
    $flash = $_SESSION['flash'][$key] ?? null;
    if (!is_string($flash) || $flash === '') {
        return null;
    }
    unset($_SESSION['flash'][$key]);
    return $flash;
}

function normalizeUserId(mixed $value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_string($value) && ctype_digit($value)) {
        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }
    return null;
}

function render(string $view, array $data = []): void
{
    if ($view === '' || str_contains($view, '..') || str_ends_with($view, '.php')) {
        throw new RuntimeException("Некорректное имя шаблона: {$view}");
    }

    $viewPath = ROOT_PATH . '/src/Views/' . trim($view, '/') . '.php';

    if (!is_file($viewPath)) {
        throw new RuntimeException("Шаблон не найден: {$view}. Ожидался путь: {$viewPath}");
    }

    extract($data, EXTR_SKIP);
    require $viewPath;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * `$price` — строка DECIMAL из БД (PDO без emulated prepares отдаёт
 * DECIMAL как строку). Приведение к int — обычный string-to-int парсинг
 * PHP, не через float (`php.md`: деньги никогда не float).
 */
function formatPrice(string $price): string
{
    return number_format((int) $price, 0, ',', ' ') . ' ₽';
}

function input(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

// ─── CSRF ───────────────────────────────────────────────────────────────

function csrfToken(): string
{
    ensureSessionStarted();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken(mixed $token): bool
{
    ensureSessionStarted();
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    if (!is_string($sessionToken) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function requireCsrf(): void
{
    if (!verifyCsrfToken(input('_csrf'))) {
        http_response_code(419);
        exit('419 Неверный CSRF-токен. Обновите страницу и попробуйте снова.');
    }
}

// ─── Роли и текущий пользователь ────────────────────────────────────────
// currentUser()/roleAllowed() читают только $_SESSION (id/name/role туда
// кладутся при логине и авто-входе по remember-cookie) — без похода в БД,
// чтобы requireRole() было дёшево вызывать на каждый защищённый маршрут.

function currentUser(): ?array
{
    ensureSessionStarted();
    $userId = normalizeUserId($_SESSION['user_id'] ?? null);
    if ($userId === null) {
        return null;
    }
    return [
        'id'   => $userId,
        'name' => (string) ($_SESSION['user_name'] ?? ''),
        'role' => (string) ($_SESSION['user_role'] ?? ''),
    ];
}

function roleAllowed(array $allowedRoles, ?string $role): bool
{
    return $role !== null && $role !== '' && in_array($role, $allowedRoles, true);
}

function requireRole(array $roles): void
{
    $user = currentUser();
    if ($user === null) {
        redirect('/login');
    }
    if (!roleAllowed($roles, $user['role'])) {
        http_response_code(403);
        exit('403 Доступ запрещён.');
    }
}

/**
 * Куда попадает пользователь после входа / при обращении к /login-
 * /register уже авторизованным: Покупатель — на `/` (личный кабинет,
 * Фаза 7), Менеджер/Администратор — в Панель управления (`phase-0.md`,
 * «Решения фазы»).
 */
function homeUrlForRole(?string $role): string
{
    return roleAllowed(['manager', 'admin'], $role) ? '/admin' : '/';
}

// ─── Remember me ────────────────────────────────────────────────────────
// Отдельная cookie (selector:validator), не продление cookie сессии — см.
// `database.md` (ADR-028) и `.docs/phases/phase-0.md`.

function setRememberCookie(string $selector, string $validator, int $expiresAt): void
{
    setcookie('remember_token', $selector . ':' . $validator, [
        'expires'  => $expiresAt,
        'path'     => '/',
        'secure'   => defined('APP_ENV') && APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberCookie(): void
{
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => defined('APP_ENV') && APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['remember_token']);
}

/**
 * Авто-вход по remember-cookie, если пользователь ещё не в сессии.
 * Любая аномалия (нет cookie, битый формат, просроченный/несуществующий
 * selector, неверный validator, заблокированный пользователь) —
 * молчаливый no-op с очисткой cookie, никогда не бросает ошибку.
 */
function attemptRememberLogin(): void
{
    if (isAuthenticated()) {
        return;
    }

    $cookie = $_COOKIE['remember_token'] ?? null;
    if (!is_string($cookie) || $cookie === '') {
        return;
    }

    if (!str_contains($cookie, ':')) {
        clearRememberCookie();
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if ($selector === '' || $validator === '') {
        clearRememberCookie();
        return;
    }

    require_once ROOT_PATH . '/src/Models/RememberToken.php';
    require_once ROOT_PATH . '/src/Models/User.php';

    $token = findRememberToken($selector);
    if ($token === null || strtotime((string) $token['expires_at']) < time()) {
        clearRememberCookie();
        return;
    }

    if (!hash_equals($token['token_hash'], hash('sha256', $validator))) {
        clearRememberCookie();
        return;
    }

    $user = findUserById((int) $token['user_id']);
    if ($user === null || (int) $user['is_blocked'] === 1) {
        deleteRememberTokens((int) $token['user_id']);
        clearRememberCookie();
        return;
    }

    regenerateSession();
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
}

// ─── Корзина ────────────────────────────────────────────────────────────
// Гостевая корзина живёт на отдельной cookie `cart_token`, не на PHP-
// сессии — та не переживает закрытие браузера/GC на shared-хостинге
// (`ADR-028`, `ADR-034`). Значение хранится в `cart_items.session_id`
// (`database.md`).

function cartToken(): string
{
    $token = $_COOKIE['cart_token'] ?? null;
    if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
        return $token;
    }

    $token = bin2hex(random_bytes(32));
    setcookie('cart_token', $token, [
        'expires'  => time() + CART_COOKIE_DAYS * 86400,
        'path'     => '/',
        'secure'   => defined('APP_ENV') && APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['cart_token'] = $token;

    return $token;
}

/**
 * Владелец корзины для `Models/Cart.php` — авторизованный пользователь
 * побеждает гостевой токен, если оба есть (тот же приоритет, что
 * `currentUser()` для остального приложения).
 */
function cartOwner(): array
{
    $user = currentUser();

    return $user !== null ? ['user_id' => $user['id']] : ['session_id' => cartToken()];
}

// ─── Rate limiting ──────────────────────────────────────────────────────
// Файловый счётчик в storage/cache/rate-limit/ — без Redis/Memcached,
// подходит для shared-хостинга. Ключ = действие + IP клиента.

function rateLimitStoragePath(string $action): string
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = ROOT_PATH . '/storage/cache/rate-limit';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Не удалось создать директорию: {$dir}");
    }
    return $dir . '/' . sha1($action . '|' . $ip) . '.json';
}

function tooManyAttempts(string $action, int $maxAttempts, int $decaySeconds = 60): bool
{
    $path = rateLimitStoragePath($action);
    if (!is_file($path)) {
        return false;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !isset($data['count'], $data['first_at'])) {
        return false;
    }
    if (time() - (int) $data['first_at'] > $decaySeconds) {
        return false;
    }
    return (int) $data['count'] >= $maxAttempts;
}

function hitRateLimit(string $action): void
{
    $path = rateLimitStoragePath($action);
    $data = ['count' => 1, 'first_at' => time()];

    if (is_file($path)) {
        $existing = json_decode((string) file_get_contents($path), true);
        if (is_array($existing) && isset($existing['count'], $existing['first_at'])) {
            $data = $existing;
            $data['count'] = (int) $data['count'] + 1;
        }
    }

    file_put_contents($path, json_encode($data), LOCK_EX);
}

function clearRateLimit(string $action): void
{
    $path = rateLimitStoragePath($action);
    if (is_file($path)) {
        unlink($path);
    }
}
