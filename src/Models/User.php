<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

function findUserByEmail(string $email): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, email, password_hash, phone, role, is_blocked, created_at
         FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user !== false ? $user : null;
}

function findUserById(int $id): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, email, password_hash, phone, role, is_blocked, created_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user !== false ? $user : null;
}

/**
 * Создаёт Покупателя (role='customer').
 * Возвращает null при гонке на UNIQUE(email) — вторая параллельная
 * регистрация с тем же email проигрывает конкуренцию, а не падает
 * системной ошибкой (SQLSTATE 23000 / MySQL error 1062).
 */
function createUser(string $name, string $email, string $passwordHash, string $phone): ?int
{
    try {
        $stmt = getPdo()->prepare(
            "INSERT INTO users (name, email, password_hash, phone, role)
             VALUES (:name, :email, :password_hash, :phone, 'customer')"
        );
        $stmt->execute([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'phone'         => $phone,
        ]);

        return (int) getPdo()->lastInsertId();
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            return null;
        }
        throw $e;
    }
}

/**
 * Покупатель по телефону (`FR-MGR-002`, ручное создание Заказа) —
 * только `role = 'customer'`, чтобы Менеджер не мог случайно
 * привязать Заказ к другому сотруднику. Телефон уже нормализован
 * (`normalizePhone()`) на всех путях записи (`AuthController`),
 * поэтому сравнение точное, без `LIKE`.
 */
function findCustomerByPhone(string $phone): ?array
{
    $stmt = getPdo()->prepare(
        "SELECT id, name, email, password_hash, phone, role, is_blocked, created_at
         FROM users WHERE phone = :phone AND role = 'customer' LIMIT 1"
    );
    $stmt->execute(['phone' => $phone]);
    $user = $stmt->fetch();

    return $user !== false ? $user : null;
}

function updateUserPasswordHash(int $userId, string $passwordHash): void
{
    $stmt = getPdo()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $stmt->execute(['password_hash' => $passwordHash, 'id' => $userId]);
}

/**
 * Личный кабинет (`FR-ACC-004`, Таск 3) — телефон здесь не меняется
 * (правило 3: только через Менеджера).
 */
function updateUserProfile(int $userId, string $name, string $email): void
{
    $stmt = getPdo()->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
    $stmt->execute(['name' => $name, 'email' => $email, 'id' => $userId]);
}

/**
 * Занятость email другим аккаунтом при смене email в кабинете —
 * `!= :id`, иначе Покупатель не смог бы «сменить» email сам на себя.
 */
function isEmailTakenByOther(string $email, int $userId): bool
{
    $stmt = getPdo()->prepare('SELECT 1 FROM users WHERE email = :email AND id != :id LIMIT 1');
    $stmt->execute(['email' => $email, 'id' => $userId]);

    return $stmt->fetchColumn() !== false;
}

/**
 * Получатели письма о превышении месячного лимита ИИ (`BR-AI-001`
 * правило 5, Таск 8 Фазы 9) — «Владелец» в терминах `prd.md` это
 * `role='admin'`, отдельной роли/поля для этого в схеме нет
 * (`.docs/prd.md`).
 */
function getAdminEmails(): array
{
    $stmt = getPdo()->query("SELECT email FROM users WHERE role = 'admin'");

    return array_column($stmt->fetchAll(), 'email');
}

/**
 * `/admin/users` (`FR-ADM-007`, Таск 8 Фазы 8) — только `manager`/
 * `admin`, Покупатели (`role='customer'`) в списке не участвуют.
 */
function getStaffUsers(): array
{
    $stmt = getPdo()->query(
        "SELECT id, name, email, phone, role, is_blocked, created_at
         FROM users WHERE role IN ('manager', 'admin') ORDER BY created_at DESC"
    );

    return $stmt->fetchAll();
}

/**
 * Создаёт сотрудника (`role` — `manager` или `admin`, задаёт вызывающий
 * код). Возвращает `null` при гонке на `UNIQUE(email)` — тот же
 * приём, что `createUser()`.
 */
function createStaffUser(array $data): ?int
{
    try {
        $stmt = getPdo()->prepare(
            'INSERT INTO users (name, email, password_hash, phone, role)
             VALUES (:name, :email, :password_hash, :phone, :role)'
        );
        $stmt->execute([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password_hash' => $data['password_hash'],
            'phone'         => $data['phone'],
            'role'          => $data['role'],
        ]);

        return (int) getPdo()->lastInsertId();
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            return null;
        }
        throw $e;
    }
}

/**
 * Ограничено `role IN ('manager','admin')`, как `getStaffUsers()` —
 * `/admin/users/{id}/block` не может задеть учётку Покупателя, даже
 * если в форму подставить чужой id. `rowCount() === 0` не отличить от
 * «уже было такое значение», поэтому при нём — отдельная проверка
 * существования, тот же приём, что `setReviewStatus()`.
 */
function setUserBlocked(int $id, bool $blocked): bool
{
    $stmt = getPdo()->prepare(
        "UPDATE users SET is_blocked = :is_blocked WHERE id = :id AND role IN ('manager', 'admin')"
    );
    $stmt->execute(['is_blocked' => $blocked ? 1 : 0, 'id' => $id]);

    if ($stmt->rowCount() > 0) {
        return true;
    }

    $exists = getPdo()->prepare("SELECT id FROM users WHERE id = :id AND role IN ('manager', 'admin')");
    $exists->execute(['id' => $id]);

    return $exists->fetch() !== false;
}
