<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Внешние сайты, которым разрешён `POST /api/v1/consultant`
 * (`api_clients`). `allowed_origins` — нормализованные домены через
 * перевод строки (`parseAllowedOrigins()`, `Core/ApiAuth.php`).
 */
function findActiveApiClientByKey(string $apiKey): ?array
{
    $stmt = getPdo()->prepare(
        'SELECT id, name, allowed_origins FROM api_clients WHERE api_key = :api_key AND is_active = 1 LIMIT 1'
    );
    $stmt->execute(['api_key' => $apiKey]);
    $client = $stmt->fetch();

    return $client !== false ? $client : null;
}

/**
 * Для preflight (`OPTIONS`): в нём нет ключа, поэтому проверяется
 * только, что домен вообще значится у какого-нибудь активного клиента.
 */
function getAllActiveApiOrigins(): array
{
    $rows    = getPdo()->query('SELECT allowed_origins FROM api_clients WHERE is_active = 1')->fetchAll();
    $origins = [];

    foreach ($rows as $row) {
        foreach (explode("\n", (string) $row['allowed_origins']) as $origin) {
            if ($origin !== '') {
                $origins[$origin] = $origin;
            }
        }
    }

    return array_values($origins);
}

function getApiClients(): array
{
    return getPdo()->query(
        'SELECT id, name, api_key, allowed_origins, is_active, created_at FROM api_clients ORDER BY id DESC'
    )->fetchAll();
}

function createApiClient(string $name, string $apiKey, array $origins): int
{
    $pdo  = getPdo();
    $stmt = $pdo->prepare(
        'INSERT INTO api_clients (name, api_key, allowed_origins) VALUES (:name, :api_key, :allowed_origins)'
    );
    $stmt->execute([
        'name'            => $name,
        'api_key'         => $apiKey,
        'allowed_origins' => implode("\n", $origins),
    ]);

    return (int) $pdo->lastInsertId();
}

function setApiClientActive(int $id, bool $isActive): bool
{
    $stmt = getPdo()->prepare('UPDATE api_clients SET is_active = :is_active WHERE id = :id');
    $stmt->execute(['is_active' => $isActive ? 1 : 0, 'id' => $id]);

    return $stmt->rowCount() > 0;
}
