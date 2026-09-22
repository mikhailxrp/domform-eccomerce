<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Лог переписки Консультанта/Подбора диалогом (`ai_chat_logs`,
 * `FR-AI-003`/`FR-AI-004`, `ADR-050`, Таск 5 Фазы 9) — 3 месяца
 * хранения (`NFR-AI-*`), без фильтрации ПДн (`Q-026`).
 */
function logAiChatMessage(string $conversationId, string $assistant, string $role, string $message): void
{
    $stmt = getPdo()->prepare(
        'INSERT INTO ai_chat_logs (conversation_id, assistant, role, message)
         VALUES (:conversation_id, :assistant, :role, :message)'
    );
    $stmt->execute([
        'conversation_id' => $conversationId,
        'assistant'       => $assistant,
        'role'            => $role,
        'message'         => $message,
    ]);
}

function deleteOldAiChatLogs(int $days): int
{
    $stmt = getPdo()->prepare('DELETE FROM ai_chat_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
    $stmt->execute(['days' => $days]);

    return $stmt->rowCount();
}

/**
 * Вероятностный вызов чистки при записи (1 к `AI_CHAT_LOG_GC_DIVISOR`)
 * — тот же приём, что `session.gc_probability`/`gc_divisor` в PHP:
 * shared-хостинг может не дать отдельный cron, поэтому чистка не
 * зависит от того, зайдёт ли кто-то в Панель управления вовремя.
 */
function maybeCleanupAiChatLogs(): void
{
    if (mt_rand(1, AI_CHAT_LOG_GC_DIVISOR) === 1) {
        deleteOldAiChatLogs(AI_CHAT_LOG_RETENTION_DAYS);
    }
}
