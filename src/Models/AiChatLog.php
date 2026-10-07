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

/**
 * Последние `$limit` реплик диалога в хронологическом порядке — для
 * API без сессии: историю хранит сама БД, не `$_SESSION`.
 *
 * @return list<array{role: string, content: string}>
 */
function getAiChatHistory(string $conversationId, int $limit): array
{
    $stmt = getPdo()->prepare(
        'SELECT role, message FROM (
             SELECT id, role, message FROM ai_chat_logs
             WHERE conversation_id = :conversation_id AND assistant = \'consultant\'
             ORDER BY id DESC
             LIMIT :limit
         ) AS last_messages
         ORDER BY id ASC'
    );
    $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map(
        static fn (array $row): array => ['role' => $row['role'], 'content' => $row['message']],
        $stmt->fetchAll()
    );
}

function countAiChatQuestions(string $conversationId): int
{
    $stmt = getPdo()->prepare(
        'SELECT COUNT(*) FROM ai_chat_logs
         WHERE conversation_id = :conversation_id AND assistant = \'consultant\' AND role = \'user\''
    );
    $stmt->execute(['conversation_id' => $conversationId]);

    return (int) $stmt->fetchColumn();
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
