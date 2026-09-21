<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Database.php';

/**
 * Журнал СМС-уведомлений (`ADR-043`) — только запись/чтение, без
 * отправки: реальный провайдер не подключается, `Services/Sms.php`
 * пишет сюда факт «СМС ушла».
 */

function logSmsNotification(int $orderId, string $phone, string $event, string $message): void
{
    $stmt = getPdo()->prepare('
        INSERT INTO sms_notifications (order_id, phone, event, message)
        VALUES (:order_id, :phone, :event, :message)
    ');
    $stmt->execute([
        'order_id' => $orderId,
        'phone'    => $phone,
        'event'    => $event,
        'message'  => $message,
    ]);
}

/**
 * Блок «Уведомления» на странице Заказа в Панели управления (Таск 9
 * Фазы 7, `AC-05`) — в порядке времени.
 */
function getOrderSmsNotifications(int $orderId): array
{
    $stmt = getPdo()->prepare('
        SELECT * FROM sms_notifications
        WHERE order_id = :order_id
        ORDER BY created_at ASC, id ASC
    ');
    $stmt->execute(['order_id' => $orderId]);

    return $stmt->fetchAll();
}
