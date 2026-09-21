<?php

declare(strict_types=1);

require_once ROOT_PATH . '/src/Core/Logger.php';
require_once ROOT_PATH . '/src/Core/Notification.php';
require_once ROOT_PATH . '/src/Models/User.php';
require_once ROOT_PATH . '/src/Models/SmsNotification.php';

/**
 * СМС — журнал-заглушка вместо провайдера (`ADR-043`): вместо реальной
 * отправки пишет текст в `app.log` и строку в `sms_notifications`.
 * Правило 7 `FR-NOTIF-001` — Заказ не зависит от уведомления, поэтому
 * функция никогда не бросает исключение наружу и вызывается после
 * commit перехода статуса (Таск 9 Фазы 7).
 */
function sendOrderSms(array $order, string $event): void
{
    try {
        $user = $order['user_id'] !== null ? findUserById((int) $order['user_id']) : null;
        $phone = orderNotificationPhone($order, $user);

        if ($phone === null) {
            logWarning('СМС не отправлена: у Заказа нет телефона', ['order_id' => $order['id'], 'event' => $event]);
            return;
        }

        $message = smsMessageForEvent($event, (int) $order['id'], $order['fulfillment_method']);

        logInfo('СМС (заглушка)', ['order_id' => $order['id'], 'phone' => maskPhone($phone), 'event' => $event]);
        logSmsNotification((int) $order['id'], $phone, $event, $message);
    } catch (Throwable $e) {
        logError('Не удалось зафиксировать СМС-уведомление', [
            'order_id' => $order['id'] ?? null,
            'event'    => $event,
            'error'    => $e->getMessage(),
        ]);
    }
}

/**
 * `+7900***0000` — первые 4 и последние 4 символа видны, середина
 * скрыта; для более коротких номеров скрывает всё, кроме последних 2.
 */
function maskPhone(string $phone): string
{
    $length = mb_strlen($phone);

    if ($length <= 6) {
        return str_repeat('*', max($length - 2, 0)) . mb_substr($phone, -2);
    }

    return mb_substr($phone, 0, 4) . str_repeat('*', $length - 8) . mb_substr($phone, -4);
}
