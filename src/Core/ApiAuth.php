<?php

declare(strict_types=1);

/**
 * Доступ внешних сайтов к `POST /api/v1/consultant` — чистые функции
 * без БД: формат ключа, нормализация и сверка `Origin`, CORS-заголовки,
 * идентификатор диалога. Ключ клиента — публичный (лежит в `<script>`
 * чужого сайта), поэтому сам по себе не защищает: его привязывают к
 * списку разрешённых доменов (`api_clients.allowed_origins`), а защиту
 * от накрутки держат лимиты запросов и месячный бюджет ИИ.
 */

const API_KEY_PREFIX     = 'dfk_';
const API_CLIENT_NAME_MAX = 100;

function generateApiKey(): string
{
    return API_KEY_PREFIX . bin2hex(random_bytes(16));
}

function isValidApiKeyFormat(string $key): bool
{
    return preg_match('/^' . API_KEY_PREFIX . '[a-f0-9]{32}\z/', $key) === 1;
}

/**
 * `scheme://host[:port]` в нижнем регистре, без пути/запроса/логина;
 * порт по умолчанию отбрасывается — браузер его в `Origin` не шлёт.
 * Всё остальное (в том числе `null` из sandbox-iframe) — `null`.
 */
function normalizeOrigin(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $parts = parse_url($value);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }

    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return null;
    }

    if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
        return null;
    }

    $port        = $parts['port'] ?? null;
    $defaultPort = $scheme === 'https' ? 443 : 80;
    $portSuffix  = $port !== null && $port !== $defaultPort ? ':' . $port : '';

    return $scheme . '://' . strtolower($parts['host']) . $portSuffix;
}

/**
 * Список из поля формы/колонки — по одному на строку, допустимы также
 * запятая и пробел. Невалидные записи молча отбрасываются; поддомены
 * и маски (`*.site.ru`) не поддерживаются — каждый домен указывается явно.
 *
 * @return list<string>
 */
function parseAllowedOrigins(string $raw): array
{
    $items   = preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $origins = [];

    foreach ($items as $item) {
        $origin = normalizeOrigin($item);
        if ($origin !== null) {
            $origins[$origin] = $origin;
        }
    }

    return array_values($origins);
}

/**
 * @param list<string> $allowedOrigins уже нормализованные (`parseAllowedOrigins()`)
 */
function isOriginAllowed(?string $origin, array $allowedOrigins): bool
{
    if ($origin === null) {
        return false;
    }

    $normalized = normalizeOrigin($origin);

    return $normalized !== null && in_array($normalized, $allowedOrigins, true);
}

/**
 * Заголовки ответа для запроса с проверенного `Origin` — отражается
 * ровно он, не `*`: ответ API содержит данные заказа, открывать его
 * любому сайту нельзя.
 *
 * @return list<string>
 */
function corsHeadersForOrigin(string $origin): array
{
    return [
        'Access-Control-Allow-Origin: ' . $origin,
        'Vary: Origin',
        'Access-Control-Allow-Methods: POST, OPTIONS',
        'Access-Control-Allow-Headers: Content-Type',
        'Access-Control-Max-Age: 600',
    ];
}

function generateConversationId(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Формат колонки `ai_chat_logs.conversation_id` (`CHAR(32)`, hex).
 */
function isValidConversationId(string $id): bool
{
    return preg_match('/^[a-f0-9]{32}\z/', $id) === 1;
}

/**
 * Ссылки на товары в ответе API должны быть абсолютными — виджет
 * показывается на чужом домене, относительный `/product/...` вёл бы
 * на сам чужой сайт.
 */
function absoluteUrl(string $baseUrl, string $path): string
{
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Форма «Ключи для внешних сайтов» — `true` в поле значит ошибку, как
 * у `validateAiSettingsInput()`. Нужен хотя бы один валидный домен.
 *
 * @return array{name: bool, origins: bool}
 */
function validateApiClientInput(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));

    return [
        'name'    => $name === '' || mb_strlen($name) > API_CLIENT_NAME_MAX,
        'origins' => parseAllowedOrigins((string) ($input['allowed_origins'] ?? '')) === [],
    ];
}
