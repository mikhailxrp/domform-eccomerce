<?php

declare(strict_types=1);

/**
 * Временный ручной инструмент проверки DoD Таска 1 Фазы 9 — реальный
 * вызов каждого настроенного класса ИИ через `aiComplete()`. Не часть
 * постоянного кода приложения — ни один Controller/Model его не
 * подключает; можно удалить после сдачи таска.
 *
 * Запуск: php database/ai-smoke-test.php
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/src/Services/Ai/ai.php';

$testMessages = [
    ['role' => 'user', 'content' => 'Ответь ровно одним словом: тест.'],
];

$seenClasses = [];

foreach (AI_ASSISTANTS as $assistant => $taskClass) {
    // Один помощник на класс задачи достаточно для smoke-теста —
    // остальные помощники того же класса используют того же провайдера.
    if (isset($seenClasses[$taskClass])) {
        continue;
    }
    $seenClasses[$taskClass] = true;

    echo "— класс «{$taskClass}» (помощник «{$assistant}»)…\n";

    if (!aiClassAvailable($taskClass)) {
        echo "  недоступен: ключ не задан в .env\n\n";
        continue;
    }

    $result = aiComplete($assistant, $testMessages);

    if ($result === null) {
        echo "  ❌ вызов не удался — смотри storage/logs/app.log\n\n";
        continue;
    }

    echo '  ✅ ответ: ' . trim($result['text']) . "\n";
    echo "     токены: вход {$result['tokens_in']}, выход {$result['tokens_out']}\n\n";
}

echo "Готово. Проверь таблицу: SELECT * FROM ai_requests ORDER BY id DESC LIMIT 5;\n";
