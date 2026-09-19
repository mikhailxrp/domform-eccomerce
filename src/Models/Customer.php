<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Validation.php';

/**
 * Клиенты Панели управления (`FR-ADM-002`, `FR-MGR-004`, Таск 6
 * Фазы 4) — Покупатели (`users.role='customer'`) и Гости (уникальный
 * `orders.guest_phone` без учётной записи с этим телефоном) в одном
 * списке. После `linkGuestOrdersToUser()` (`Order.php`) `guest_*`
 * обнуляются на привязанных Заказах, поэтому задвоения «аккаунт» +
 * «гость» с одним и тем же телефоном не возникает без дополнительной
 * логики здесь.
 */

/**
 * Условие поиска для одной ветки `UNION` — общее для `getCustomers()`
 * и `countCustomers()`, чтобы список и счётчик не разошлись по
 * условиям (тот же принцип, что `buildAdminOrderFilterConditions()` в
 * `Order.php`). `$paramSuffix` держит имена параметров уникальными
 * между веткой Покупателей и веткой Гостей — этот проект не полагается
 * на переиспользование одного named-параметра в разных позициях
 * запроса (см. тот же `buildAdminOrderFilterConditions()`). Телефон
 * ищется точным совпадением после `normalizePhone()`, не `LIKE` — как
 * и везде в Панели управления, только нормализованный ввод даёт
 * осмысленный результат.
 */
function buildCustomerSearchCondition(string $search, string $nameColumn, string $phoneColumn, string $paramSuffix): array
{
    $search = trim($search);
    if ($search === '') {
        return ['', []];
    }

    $likeTerm = addcslashes($search, '\\_%') . '%';

    $conditions = ["{$nameColumn} LIKE :name_term_{$paramSuffix}"];
    $params     = ["name_term_{$paramSuffix}" => $likeTerm];

    $normalizedPhone = normalizePhone($search);
    if ($normalizedPhone !== '') {
        $conditions[]                            = "{$phoneColumn} = :phone_term_{$paramSuffix}";
        $params["phone_term_{$paramSuffix}"] = $normalizedPhone;
    }

    return [' AND (' . implode(' OR ', $conditions) . ')', $params];
}

/**
 * `CAST(u.id AS CHAR) COLLATE utf8mb4_unicode_ci` — без явного `COLLATE`
 * `CAST` берёт collation соединения (`utf8mb4_general_ci`), а не
 * колонки, и `UNION` с `o.guest_phone` (`utf8mb4_unicode_ci`, как все
 * таблицы проекта — `database/install.php`) падает с «Illegal mix of
 * collations» — воспроизведено на реальной БД при живой проверке.
 */
function getCustomers(string $search, int $page, int $perPage): array
{
    $pdo = getPdo();

    [$userCondition, $userParams]   = buildCustomerSearchCondition($search, 'u.name', 'u.phone', 'u');
    [$guestCondition, $guestParams] = buildCustomerSearchCondition($search, 'o.guest_name', 'o.guest_phone', 'g');

    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        (SELECT
            'user' AS type,
            CAST(u.id AS CHAR) COLLATE utf8mb4_unicode_ci AS `key`,
            u.name AS name,
            u.phone AS phone,
            (SELECT COUNT(*) FROM orders eo WHERE eo.user_id = u.id) AS order_count
        FROM users u
        WHERE u.role = 'customer'{$userCondition})

        UNION ALL

        (SELECT
            'guest' AS type,
            o.guest_phone AS `key`,
            MAX(o.guest_name) AS name,
            o.guest_phone AS phone,
            COUNT(*) AS order_count
        FROM orders o
        WHERE o.user_id IS NULL AND o.guest_phone IS NOT NULL{$guestCondition}
        GROUP BY o.guest_phone)

        ORDER BY name ASC
        LIMIT :limit OFFSET :offset
    ");

    foreach (array_merge($userParams, $guestParams) as $key => $value) {
        $stmt->bindValue(":{$key}", $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function countCustomers(string $search): int
{
    [$userCondition, $userParams]   = buildCustomerSearchCondition($search, 'u.name', 'u.phone', 'u');
    [$guestCondition, $guestParams] = buildCustomerSearchCondition($search, 'o.guest_name', 'o.guest_phone', 'g');

    $stmt = getPdo()->prepare("
        SELECT COUNT(*) FROM (
            (SELECT CAST(u.id AS CHAR) COLLATE utf8mb4_unicode_ci AS `key` FROM users u WHERE u.role = 'customer'{$userCondition})
            UNION ALL
            (SELECT o.guest_phone AS `key` FROM orders o
             WHERE o.user_id IS NULL AND o.guest_phone IS NOT NULL{$guestCondition}
             GROUP BY o.guest_phone)
        ) combined
    ");

    foreach (array_merge($userParams, $guestParams) as $key => $value) {
        $stmt->bindValue(":{$key}", $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * `$type` — `'user'`/`'guest'` из URL (`AdminCustomerController::show()`
 * уже сделал `rawurldecode()` над `$key` для Гостя). Возвращает
 * одинаковую форму для обеих веток, чтобы `customers/show.php` не
 * ветвился по типу клиента.
 */
function findCustomer(string $type, string $key): ?array
{
    return match ($type) {
        'user'  => findCustomerAccountById((int) $key),
        'guest' => findGuestCustomerByPhone($key),
        default => null,
    };
}

function findCustomerAccountById(int $id): ?array
{
    $stmt = getPdo()->prepare(
        "SELECT id, name, email, phone, created_at
         FROM users WHERE id = :id AND role = 'customer' LIMIT 1"
    );
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    if ($user === false) {
        return null;
    }

    return [
        'type'       => 'user',
        'key'        => (string) $user['id'],
        'name'       => $user['name'],
        'phone'      => $user['phone'],
        'email'      => $user['email'],
        'created_at' => $user['created_at'],
    ];
}

/**
 * Гость подтверждается наличием хотя бы одного своего (не привязанного
 * к аккаунту) Заказа с этим телефоном — отдельной таблицы клиентов-
 * гостей в схеме нет. `MAX()` на `guest_name`/`guest_email` — на
 * случай расхождений между заказами одного телефона (опечатка в имени
 * во втором звонке и т.п.), точная история не строится (`Q-016`).
 */
function findGuestCustomerByPhone(string $phone): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT MAX(guest_name) AS name, guest_phone AS phone, MAX(guest_email) AS email, MIN(created_at) AS created_at
         FROM orders
         WHERE user_id IS NULL AND guest_phone = :phone
         GROUP BY guest_phone'
    );
    $stmt->execute(['phone' => $phone]);
    $guest = $stmt->fetch();

    if ($guest === false) {
        return null;
    }

    return ['type' => 'guest', 'key' => $phone] + $guest;
}

function getCustomerOrders(string $type, string $key): array
{
    if ($type === 'user') {
        $stmt = getPdo()->prepare(
            'SELECT id, status, total, payment_status, created_at
             FROM orders WHERE user_id = :user_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => (int) $key]);

        return $stmt->fetchAll();
    }

    if ($type === 'guest') {
        $stmt = getPdo()->prepare(
            'SELECT id, status, total, payment_status, created_at
             FROM orders WHERE user_id IS NULL AND guest_phone = :phone
             ORDER BY created_at DESC'
        );
        $stmt->execute(['phone' => $key]);

        return $stmt->fetchAll();
    }

    return [];
}
