<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Демо-раздел «Интеграции» — CRM/учёт/телефония/рассылки. Переключатель
 * ничего не подключает, только сохраняет визуальное состояние
 * (`.docs/dev-log.md`, тот же приём, что `/payment/stub`, `ADR-018`).
 */
function getIntegrations(): array
{
    $stmt = getPdo()->query(
        'SELECT id, code, category, name, description, icon, is_enabled, sort_order
         FROM integrations
         ORDER BY category, sort_order, id'
    );

    return $stmt->fetchAll();
}

/**
 * `$enabledCodes` — коды интеграций, присланные формой как включённые.
 * Обновляются только коды, реально существующие в таблице — попытка
 * прислать произвольный код не создаёт строку и ни на что не влияет.
 */
function updateIntegrations(array $enabledCodes): void
{
    $integrations = getIntegrations();

    $stmt = getPdo()->prepare(
        'UPDATE integrations SET is_enabled = :is_enabled WHERE code = :code'
    );

    foreach ($integrations as $integration) {
        $stmt->execute([
            'is_enabled' => in_array($integration['code'], $enabledCodes, true) ? 1 : 0,
            'code'       => $integration['code'],
        ]);
    }
}
