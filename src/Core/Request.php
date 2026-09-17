<?php

declare(strict_types=1);

/**
 * Хелперы для работы с HTTP-запросом.
 */

function requestMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function requestPath(): string
{
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    return is_string($path) ? rtrim($path, '/') ?: '/' : '/';
}

function isPost(): bool
{
    return requestMethod() === 'POST';
}

function isGet(): bool
{
    return requestMethod() === 'GET';
}

/**
 * Фрагмент вместо полной страницы — конвенция каталога (`phase-1.md`,
 * «Решения фазы»): без отдельного JSON API, тот же Controller отдаёт
 * кусок HTML по заголовку `X-Requested-With: fetch` из `app.js`.
 */
function isFetchRequest(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}
