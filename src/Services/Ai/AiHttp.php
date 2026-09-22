<?php

declare(strict_types=1);

/**
 * Один HTTP POST-вызов на все провайдеры ИИ — таймаут задаётся
 * вызывающим кодом (`AI_TIMEOUT_SECONDS`, ответ нужен за ≤10 секунд,
 * `NFR-AI-*`), ретраев нет: повторный вызов только удлиняет ожидание
 * Покупателя, а не повышает шанс успеха при реальном отказе
 * провайдера. Бросает `RuntimeException` при сетевой ошибке или
 * HTTP-статусе вне 200–299 — вызывающий код (`Services/Ai/ai.php`)
 * обязан перехватывать её и превращать в `null`, не пробрасывать
 * дальше (`FR-AI-*`: недоступность провайдера не ломает сайт).
 */
function aiHttpPostJson(string $url, array $headers, array $payload, int $timeoutSeconds): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Не удалось инициализировать HTTP-запрос к ИИ-провайдеру');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
    ]);

    $body   = curl_exec($ch);
    $errno  = curl_errno($ch);
    $error  = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $errno !== 0) {
        throw new RuntimeException("Сетевая ошибка запроса к ИИ-провайдеру: {$error}");
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("ИИ-провайдер вернул HTTP {$status}: " . mb_substr((string) $body, 0, 200));
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('ИИ-провайдер вернул невалидный JSON');
    }

    return $decoded;
}
