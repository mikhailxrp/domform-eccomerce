<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';
require_once ROOT_PATH . '/src/Core/Settings.php';

/**
 * Реквизиты магазина (`settings`, `ADR-045`) — key/value, whitelist
 * ключей задан `SETTING_KEYS` в `Core/Settings.php`.
 */

function getAllSettings(): array
{
    $stmt = getPdo()->query('SELECT `key`, value FROM settings');

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['key']] = $row['value'];
    }

    return $settings;
}

/**
 * Обновляет только ключи из `SETTING_KEYS`, присутствующие в
 * `$values` — неизвестный ключ POST-запроса молча игнорируется, не
 * попадает в БД. Один UPDATE на ключ в общей транзакции: реквизиты
 * должны обновиться все вместе или не обновиться вовсе, чтобы форма
 * не сохранилась наполовину при сбое посреди запроса.
 */
function updateSettings(array $values): void
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('UPDATE settings SET value = :value WHERE `key` = :key');

        foreach (array_keys(SETTING_KEYS) as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $stmt->execute(['value' => $values[$key], 'key' => $key]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
