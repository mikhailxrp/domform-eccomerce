# Current Task

## Фаза
Phase 7 — Кабинет покупателя и уведомления
(`.docs/phases/phase-7.md`), Таск 8 из 9.

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД: временный
скрипт (scratchpad) создавал тестовые Заказы (с `guest_phone` и без
телефона) и вызывал `sendOrderSms()` напрямую. Заказ с телефоном →
строка в `sms_notifications` (`phone=+79261234567`, `event=accepted`,
текст с номером Заказа) и запись в `app.log` с замаскированным
телефоном (`+792****4567`); Заказ без телефона → только `WARNING` в
логе, ни строки в БД, исключение не брошено. Тестовые Заказы удалены
после проверки. `php database/install.php` дважды подряд — без
ошибок. `composer test` — 337/337 (было 325, +12 `NotificationTest`).
Реализовано без отклонений от плана ниже.

## Задача
Основа `FR-NOTIF-001` без подключения к Заказам: чистые функции
маппинга статус → событие и текстов 5 уведомлений; заглушка
`sendOrderSms()`, которая по Заказу и событию пишет «СМС» в лог и
строку в `sms_notifications`. Реального провайдера нет и не
проектируется (демо-проект, `Q-032`). Хуки в контроллерах — Таск 9.

## Scope — что трогаем

- [x] `database/install.php` — изменить: добавить `CREATE TABLE IF NOT
      EXISTS sms_notifications` (`id`, `order_id` FK → orders ON DELETE
      CASCADE, `phone VARCHAR(20) NOT NULL`, `event VARCHAR(30) NOT
      NULL`, `message VARCHAR(500) NOT NULL`, `created_at`;
      `KEY idx_sms_notifications_order (order_id)`) — по образцу
      `addresses`/`payment_logs`
- [x] `.docs/database.md` — изменить: раздел `sms_notifications`
- [x] `.docs/planning-log.md` — изменить: ADR «СМС — журнал-заглушка
      вместо провайдера, `Q-032` для демо снят»
- [x] `src/Core/Notification.php` — создать: константы 5 событий
      (`SMS_EVENT_ACCEPTED`/`CONFIRMED`/`STATUS_CHANGED`/`READY`/
      `CANCELLED`), `smsEventForStatus(string $status, string
      $fulfillment): ?string` (`delivered` → `null`),
      `smsMessageForEvent(string $event, int $orderId, string
      $fulfillment): string` (тексты с номером Заказа, «готов к
      доставке» / «готов к выдаче» по способу получения),
      `orderNotificationPhone(array $order, ?array $user): ?string`
      (`users.phone` либо `guest_phone`) — чистые функции
- [x] `tests/Unit/NotificationTest.php` — создать
- [x] `tests/bootstrap.php` — изменить: подключить `Core/Notification.php`
- [x] `src/Services/Sms.php` — создать: `sendOrderSms(array $order,
      string $event): void` — собирает телефон и текст, `logInfo()` с
      маскированным телефоном, `logSmsNotification()`; Заказ без
      телефона → `logWarning()`, без записи; никогда не бросает
      исключения наружу (`try/catch` + `logError()`)
- [x] `src/Models/SmsNotification.php` — создать:
      `logSmsNotification(int $orderId, string $phone, string $event,
      string $message): void`, `getOrderSmsNotifications(int
      $orderId): array`

## Out of scope — не трогаем

- Хуки вызова `sendOrderSms()` в `CheckoutController`/
  `AdminOrderController` — Таск 9
- Блок «Уведомления» в `src/Views/admin/orders/show.php` — Таск 9
- `.docs/modules/ord.md` — трогается в Таске 9
- Реальный СМС-провайдер, `.env`-настройки, слот под провайдера — не
  проектируются вообще (решение фазы, `phase-7.md`)

## Definition of Done

- [x] `NotificationTest`: маппинг всех 7 статусов (`new`, `confirmed`,
      `in_production`, `ready_for_shipment` × delivery/pickup,
      `shipping`, `cancelled`, `delivered` → `null`); тексты содержат
      номер Заказа; телефон — `users.phone` при наличии пользователя,
      иначе `guest_phone`, иначе `null`
- [x] Прямой вызов `sendOrderSms()` на существующем Заказе → строка в
      `sms_notifications` и запись в `app.log`, телефон в логе
      замаскирован (`+7900***0000`)
- [x] Заказ без телефона → предупреждение в логе, строки нет, вызов не
      бросает исключение
- [x] `php database/install.php` дважды подряд — без ошибок
- [x] `composer test` зелёный, включая `NotificationTest`
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
