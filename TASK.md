# Current Task

## Фаза
Phase 7 — Кабинет покупателя и уведомления
(`.docs/phases/phase-7.md`), Таск 9 из 9 — последний таск, фаза
закрыта.

**Статус:** ✅ Завершён 21.09.2026 — проверено живым HTTP на реальной
БД (`php -S` + `curl`, временный Менеджер и тестовые Заказы, удалены
после проверки). Ручной Заказ с предоплатой сразу → 2 строки
(«принят», «подтверждён»); полный проход
`in_production → ready_for_shipment → shipping → delivered` →
ровно 5 строк, блок «Уведомления» отразил их в верном порядке;
запрещённый переход и повторное «Отметить предоплату» — без лишних
строк; отдельный гостевой Заказ → «принят» с `guest_phone`, отмена из
`new` → «отменён». Искусственная ошибка внутри `sendOrderSms()`
(нарушение FK) не вышла наружу — `ERROR` в `app.log`, без 500.
`composer test` — 337/337 (регрессия). Реализовано без отклонений от
плана ниже.

## Задача
`FR-NOTIF-001` целиком: уведомление фиксируется при создании Заказа
(чекаут и ручное создание Менеджером), на `confirmed` (в т.ч. через
фиксацию предоплаты), `in_production`, `ready_for_shipment`,
`shipping`, `cancelled` — всегда после commit, сбой заглушки не
блокирует переход (правило 7). На странице Заказа в Панели управления
— блок «Уведомления»: событие, телефон, текст, время (`AC-05`).

## Scope — что трогаем

- [x] `src/Controllers/CheckoutController.php` — изменить: `store()`,
      после успешного `createOrder()` — `sendOrderSms(array_merge($order,
      ['id' => $orderId]), SMS_EVENT_ACCEPTED)` (`$order` уже содержит
      `user_id`/`guest_phone`/`fulfillment_method`)
- [x] `src/Controllers/AdminOrderController.php` — изменить:
      - `store()` (ручное создание) — СМС «принят» после `createOrder()`;
        если `markOrderPrepaid()` вернул `PREPAID_RESULT_OK` и
        перечитанный `findOrderById($orderId)['status'] === 'confirmed'`
        — доп. СМС «подтверждён» (перепроверка статуса нужна, потому что
        `transitionOrderStatus()` внутри `markOrderPrepaid()` не бросает
        на запрещённом переходе)
      - `transition()` — после успешного `transitionOrderStatus()` —
        `sendOrderSms()` с `smsEventForStatus($to, $order['fulfillment_method'])`,
        если событие не `null`
      - `markPrepaid()` — та же проверка `PREPAID_RESULT_OK` +
        перечитанный статус `confirmed`, что и в `store()` (вынесено в
        общий приватный `notifyPrepaidConfirmed()`)
      - `cancel()` — после `cancelOrder()` — СМС «отменён»
      - `show()` — передаёт `$smsNotifications =
        getOrderSmsNotifications((int) $id)` в View
- [x] `src/Views/admin/orders/show.php` — изменить: блок «Уведомления»
      (по образцу карточки «Возврат и гарантия») — событие/телефон/
      текст/время, пустое состояние «уведомлений ещё не было»
- [x] `.docs/modules/ord.md` — изменить: пометка у `NOTIF` —
      «реализовано в Фазе 7 как журнал-заглушка,
      `Services/Sms.php::sendOrderSms()`, точки вызова: чекаут, ручное
      создание, `transition()`, `markPrepaid()`, `cancel()`»

## Out of scope — не трогаем

- `Core/Notification.php`, `Services/Sms.php`, `Models/SmsNotification.php`,
  таблица `sms_notifications` — готовы в Таске 8, не меняются
- Реальный СМС-провайдер — не проектируется (решение фазы)
- `markOrderPaidFull()`, `setShipping()`, работа с Резервами/Возвратами —
  вне 5 СМС-событий
- Закрытие фазы (`_status.md`, `tz-coverage.md`, `dev-log.md` — «Решения
  фазы») — отдельный шаг после этого таска

## Definition of Done

- [x] Проход Заказа `new → confirmed → in_production →
      ready_for_shipment → shipping → delivered` даёт ровно 5 строк
      `sms_notifications` (на `delivered` — нет); каждая видна в блоке
      на странице Заказа в порядке времени (`FR-NOTIF-001`, `AC-05`)
- [x] Отмена из `new`/`confirmed`/`in_production` → строка «Заказ
      отменён»
- [x] Гостевой Заказ → телефон из `guest_phone`; Заказ Покупателя —
      из `users.phone`
- [x] Повторное «Отметить предоплату» (`PREPAID_RESULT_ALREADY`) и
      проигрыш конкуренции за образец (`PREPAID_RESULT_SAMPLE_TAKEN`)
      → второго/лишнего СМС нет; запрещённый переход (`transition()`
      вернул `false`) → СМС нет (`PREPAID_RESULT_ALREADY` проверен
      живьём; `PREPAID_RESULT_SAMPLE_TAKEN` защищён тем же кодом
      `notifyPrepaidConfirmed()`, что и `ALREADY` — оба исключены одной
      проверкой `=== PREPAID_RESULT_OK`, отдельный сценарий гонки за
      образец не переигрывался — уже покрыт тестами Фазы 5)
- [x] Ручной Заказ Менеджера с предоплатой сразу → две строки: «принят»
      и «подтверждён»
- [x] Искусственная ошибка в заглушке (временно) → переход статуса
      всё равно прошёл, в `app.log` ошибка, 500 нет
- [x] `composer test` зелёный (регрессия — таск не добавляет чистую
      логику без БД)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
