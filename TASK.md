# Current Task

## Фаза
Phase 3 — Онлайн-оплата и касса (`.docs/phases/phase-3.md`, Таск 1)

**Статус:** ✅ Завершён — код реализован и проверен через `php -S` +
`curl` против реальной БД (`mikhail700.beget.tech`): заказ картой на
сайте → на `/checkout/success` ссылка «перейти к оплате» ведёт на
`/payment/stub` без id в URL, показывает номер и статус заказа; заказ
наличными → ссылки нет, прямой заход на `/payment/stub` редиректит на
`/checkout/success`; без `last_order_id` в сессии → редирект на `/`;
`composer test` 119/119 (без изменений в чистой логике — новых тестов
не требовалось); `storage/logs/app.log` — новых ошибок нет. Тестовые
заказы удалены после проверки.

## Задача
`GET /payment/stub` показывает номер и статус текущего Заказа
(резолвится через `$_SESSION['last_order_id']`, без id в URL — как
`/checkout/success`), поясняет, что это демо-проект без реального
списания, и даёт кнопку «Позвонить/WhatsApp»; на `/checkout/success`
текущий текст-заглушка про оплату картой заменяется ссылкой на
`/payment/stub`, видимой только когда `payment_method === 'card_online'`.

## Scope — что трогаем

- [x] `config/routes.php` — изменено: добавлен `GET /payment/stub` →
      `['PaymentController', 'stub']`
- [x] `src/Controllers/PaymentController.php` — создан: `stub()` —
      `ensureSessionStarted()`, `last_order_id` из сессии →
      `findOrderById()`; нет id в сессии или заказ не найден → редирект
      на `/`; `payment_method !== 'card_online'` → редирект на
      `/checkout/success`
- [x] `src/Views/payment/stub.php` — создан: по образцу
      `checkout/success.php` (шапка/подвал, breadcrumbs, `flash.php`),
      номер и статус Заказа через `orderStatusLabel()`, текст про
      демо-проект, кнопки «Позвонить»/«WhatsApp»
      (`SHOP_PHONE`/`SHOP_WHATSAPP_URL`), ссылка «Вернуться к заказу» →
      `/checkout/success`
- [x] `src/Views/checkout/success.php` — изменён: блок
      `payment_method === 'card_online'` — вместо текста добавлена
      ссылка на `/payment/stub`

## Out of scope — не трогаем

- Таск 2 этой же фазы: `markOrderPrepaid()`/`markOrderPaidFull()`,
  `Core/Payment.php`, `PaymentTest.php` — отдельный таск
- Реальная интеграция с ЮMoney, вебхук, `payment_logs` — не
  реализуется в портфолио-версии (`ADR-018`)
- Фискализация Атол (`FR-PAY-005`) — не реализуется (`Q-007` снят)
- UI Менеджера для отметки оплаты — Фаза 4
- Любые изменения в `CheckoutController`, `Models/Order.php`,
  статусной модели заказа — вне scope этого таска

## Definition of Done

- [x] Заказ с `payment_method='card_online'` → на `/checkout/success`
      видна ссылка на `/payment/stub`; переход показывает тот же номер
      и статус Заказа, без id в URL (проверено curl, заказ №15)
- [x] Заказ с `payment_method` `cash`/`bank_transfer` → ссылки на
      `/payment/stub` нет; прямой заход на `/payment/stub` для такого
      Заказа редиректит на `/checkout/success` (проверено curl, заказ №16)
- [x] Без `last_order_id` в сессии (новая вкладка/другой браузер) →
      `/payment/stub` редиректит на `/`, чужой Заказ недоступен
      (проверено curl без cookies)
- [x] Кнопка «Позвонить/WhatsApp» видна и не отправляет никакую форму
      (обычные `<a>`-ссылки, `tel:` / внешняя ссылка)
- [x] Страница читаема на 320px+ (переиспользует те же секции/классы,
      что `checkout/success.php`), вывод через `e()`, нет inline-стилей
- [x] Проверить `.docs/dod-global.md` — `composer test` 119/119, новых
      записей в `storage/logs/app.log` нет, CSRF не требуется (GET-запрос
      без изменения данных), SQL/бизнес-логики в `PaymentController`/
      `stub.php` нет

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
