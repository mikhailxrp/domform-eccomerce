<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Книга сохранённых адресов Покупателя (`FR-ACC-002`, `.docs/phases/
 * phase-7.md`, Таск 4, `ADR-042`). «Ровно один основной адрес» на
 * Покупателя — инвариант этого файла, не constraint БД (в отличие от
 * `reserves.active_variant_id`, `ADR-007`): `is_default` — пользова-
 * тельское предпочтение одного человека, не ресурс, за который
 * конкурируют параллельные запросы разных пользователей.
 */

function getUserAddresses(int $userId): array
{
    $stmt = getPdo()->prepare('
        SELECT * FROM addresses
        WHERE user_id = :user_id
        ORDER BY is_default DESC, created_at ASC, id ASC
    ');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

/**
 * Адрес в кабинете — только свой (`dod-global.md`: подмена чужого id
 * не должна открывать чужие данные); `user_id` в условии `WHERE`, не
 * проверка постфактум.
 */
function findUserAddress(int $id, int $userId): ?array
{
    $stmt = getPdo()->prepare('SELECT * FROM addresses WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['id' => $id, 'user_id' => $userId]);
    $address = $stmt->fetch();

    return $address !== false ? $address : null;
}

function countUserAddresses(int $userId): int
{
    $stmt = getPdo()->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * `$data` — уже провалидированные строки `validateAddressInput()`;
 * необязательные поля переводятся в `NULL`, только если пусты — здесь,
 * не в Controller (это вопрос хранения, не валидности ввода). Первый
 * адрес Покупателя автоматически становится основным — считать «первый
 * ли» и вставлять строку в одной транзакции, чтобы не разойтись со
 * счётчиком при повторном вызове.
 */
function createAddress(array $data): int
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $isFirst = countUserAddresses($data['user_id']) === 0;

        $stmt = $pdo->prepare('
            INSERT INTO addresses (user_id, title, city, street, house, apartment, comment, is_default)
            VALUES (:user_id, :title, :city, :street, :house, :apartment, :comment, :is_default)
        ');
        $stmt->execute([
            'user_id'    => $data['user_id'],
            'title'      => $data['title'] !== '' ? $data['title'] : null,
            'city'       => $data['city'],
            'street'     => $data['street'],
            'house'      => $data['house'],
            'apartment'  => $data['apartment'] !== '' ? $data['apartment'] : null,
            'comment'    => $data['comment'] !== '' ? $data['comment'] : null,
            'is_default' => $isFirst ? 1 : 0,
        ]);

        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * `is_default` здесь не трогается — смена основного адреса идёт только
 * через `setDefaultAddress()`, отдельным действием формы.
 */
function updateAddress(int $id, int $userId, array $data): bool
{
    $stmt = getPdo()->prepare('
        UPDATE addresses
        SET title = :title, city = :city, street = :street, house = :house,
            apartment = :apartment, comment = :comment
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute([
        'title'     => $data['title'] !== '' ? $data['title'] : null,
        'city'      => $data['city'],
        'street'    => $data['street'],
        'house'     => $data['house'],
        'apartment' => $data['apartment'] !== '' ? $data['apartment'] : null,
        'comment'   => $data['comment'] !== '' ? $data['comment'] : null,
        'id'        => $id,
        'user_id'   => $userId,
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * Удаление основного адреса переназначает основным самый ранний из
 * оставшихся — «среди адресов Покупателя всегда ≤ 1 `is_default = 1`»
 * (`dod-global.md`, DoD таска) не должно нарушаться даже на миг между
 * запросами. `false` — адрес не найден/не свой, ничего не удалено.
 */
function deleteAddress(int $id, int $userId): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT is_default FROM addresses WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $address = $stmt->fetch();

        if ($address === false) {
            $pdo->rollBack();
            return false;
        }

        $deleteStmt = $pdo->prepare('DELETE FROM addresses WHERE id = :id AND user_id = :user_id');
        $deleteStmt->execute(['id' => $id, 'user_id' => $userId]);

        if ((int) $address['is_default'] === 1) {
            $promoteStmt = $pdo->prepare('
                UPDATE addresses
                SET is_default = 1
                WHERE user_id = :user_id
                ORDER BY created_at ASC, id ASC
                LIMIT 1
            ');
            $promoteStmt->execute(['user_id' => $userId]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Сброс остальных перед установкой — иначе на миг между двумя UPDATE
 * могло бы оказаться два `is_default = 1`, если сначала выставить
 * новый, потом сбрасывать старые (порядок важен даже внутри одной
 * транзакции — читатели в других соединениях InnoDB REPEATABLE READ
 * всё равно не увидят строки до COMMIT, но порядок операций здесь для
 * ясности кода, не для гонки).
 */
function setDefaultAddress(int $id, int $userId): bool
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT id FROM addresses WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        if ($stmt->fetchColumn() === false) {
            $pdo->rollBack();
            return false;
        }

        $resetStmt = $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :user_id');
        $resetStmt->execute(['user_id' => $userId]);

        $setStmt = $pdo->prepare('UPDATE addresses SET is_default = 1 WHERE id = :id');
        $setStmt->execute(['id' => $id]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
