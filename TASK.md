# Current Task

## Фаза
Phase 2 — Корзина и оформление заказа (`.docs/phases/phase-2.md`, Таск 4)

**Статус:** ✅ Завершён — код реализован и проверен через `php -S` +
`curl` против реальной БД (`mikhail700.beget.tech`): гость оформил Заказ
(самовывоз/наличными) — `guest_*` заполнены, `user_id IS NULL`,
`delivery_address IS NULL`; доставка/банковский перевод с Email —
`delivery_address` сохранён; двойной сабмит тем же `checkout_token` не
создал второй Заказ; 6-я попытка оформления за минуту заблокирована
(`tooManyAttempts`), 5 предыдущих не создали Заказов; пустое
обязательное поле «Комментарий» → форма переотрисована с ошибкой, Заказ
не создан; гость с email → зарегистрировался тем же email →
`orders.user_id` проставлен задним числом, `guest_*` очищены (другие
гостевые заказы с иным email не затронуты); `/checkout/success` без
`last_order_id` → редирект на `/`; откат транзакции при сбое на вставке
`order_items` подтверждён (см. «Отклонения от плана», п.3) — строка в
`orders` не остаётся; тестовые данные (заказы, гостевой аккаунт, rate
limit) удалены после проверки; `composer test` 98/98 (без новых unit-
тестов — весь новый код ходит в БД, `php.md`: «Тесты на Model-функции...
которые ходят в БД — отдельная категория... не заводи эту
инфраструктуру заранее»).

## Задача
`POST /checkout` создаёт Заказ в статусе «Новый» (`payment_status =
'unpaid'`, `shipping_cost = NULL`, `prepaid_amount = NULL`) со снэпшотом
позиций в одной транзакции и суммой, посчитанной на сервере; Гость →
`guest_*`, Покупатель → `user_id` (`FR-ORD-005`); опция «Создать
аккаунт» регистрирует и входит; корзина очищается; страница «Заказ
принят»; защита от двойной отправки и rate-limit; гостевые Заказы
привязываются к аккаунту при регистрации тем же email.

## Находки перед кодом

1. **В схеме нет поля для «Комментарий».** ТЗ (`00-input/tz.md`,
   FR-CHK-001, п.3-4) требует обязательное текстовое поле «Комментарий»
   на форме оформления — добавлено в Таске 3. Но ни `database.md`, ни
   `database/install.php` не заводят для него колонку в `orders` — это
   пропуск от `sdd-schema`, не домысел. Без колонки `createOrder()`
   физически некуда его сохранить — добавлена `orders.comment TEXT
   NOT NULL` (по аналогии с тем, как Таск 1 этой фазы уже добавлял
   `cart_items.price_snapshot` через ADR).
2. **В форме `/checkout` (Таск 3) не было скрытого поля
   `checkout_token`.** Контроллер его генерирует в сессию
   (`CheckoutController::index()`), но `checkout/index.php` не выводил
   `<input type="hidden" name="checkout_token">` — без него
   `POST /checkout` не смог бы свериться с сессией для защиты от
   двойной отправки. Добавлено в существующий файл.

## Scope — что трогаем

- [x] `database/install.php` — изменено: `orders.comment TEXT NOT NULL`
      в `CREATE TABLE` + идемпотентный `ALTER TABLE ... ADD COLUMN`
      блок для уже развёрнутых БД (по образцу `cart_items.price_snapshot`
      из Таска 1) — выполнено дважды подряд без ошибок
- [x] `.docs/database.md` — изменено: колонка `comment` в `orders`
- [x] `.docs/planning-log.md` — изменено: `ADR-035` (комментарий
      Покупателя — обязательное поле ТЗ, пропущено при `sdd-schema`)
- [x] `src/Models/Order.php` — создан: `createOrder(array $order,
      array $items): ?int` (транзакция: `SELECT ... FOR UPDATE` по
      каждому Варианту → снэпшот `product_name`/`variant_sku`/
      `variant_material`/`variant_mechanism`/`variant_color`/`price`/
      `quantity`, цена перечитана из `product_variants` внутри
      транзакции; повторная проверка активного резерва на образец —
      `rollBack()`+`null`, если уже занят; `validateOrderContact()` —
      «ровно один источник контактов»; `INSERT orders` → `INSERT
      order_items` → `commit`/`rollBack`+rethrow), `findOrderById(int
      $id): ?array`, `getOrderItems(int $orderId): array`,
      `linkGuestOrdersToUser(string $email, int $userId): int`
      (очищает `guest_*` при привязке)
- [x] `src/Controllers/CheckoutController.php` — изменён: добавлены
      `store()` и `success()`; `index()`/`renderCheckoutPage()`
      отрефакторены в общий приватный метод (нужен и для
      переотрисовки формы с ошибками из `store()`, и для исходного
      `index()`) — не «рефакторинг попутно», а прямая необходимость
      этого таска
- [x] `src/Views/checkout/index.php` — изменено: скрытое поле
      `checkout_token`
- [x] `src/Views/checkout/success.php` — создан
- [x] `src/Controllers/AuthController.php` — изменён: `register()` →
      `linkGuestOrdersToUser()` после `createUser()`
- [x] `config/routes.php` — изменено: `POST /checkout`,
      `GET /checkout/success`

Отклонение от изначального Scope, обнаруженное в процессе реализации
(причина — в «Отклонения от плана»):
- [x] `src/Core/Checkout.php` — изменено: добавлен `require_once
      Core/Validation.php`

## Отклонения от плана

1. **`src/Core/Checkout.php` не подключал `Core/Validation.php`,
   хотя вызывает его функции.** `normalizeCheckoutInput()`/
   `validateCheckoutInput()` (Таск 3) используют `normalizePhone()`/
   `validatePhone()`/`validateEmail()`/`validatePassword()` из
   `Core/Validation.php`, но ни `Core/Checkout.php`, ни
   `CheckoutController.php` его не требовали — баг молчал весь Таск 3,
   потому что `GET /checkout` (`index()`) не вызывает
   `normalizeCheckoutInput()`, только `POST /checkout` (`store()`, этот
   таск). Обнаружено первым же ручным тестом (`Call to undefined
   function normalizePhone()`, `storage/logs/app.log`). Исправлено
   добавлением `require_once ROOT_PATH . '/src/Core/Validation.php';`
   в начало `Core/Checkout.php` — тот файл, что реально зависит от этих
   функций, получает свою зависимость сам, не полагаясь на то, что её
   уже подключил кто-то другой.
2. **`CheckoutController::index()` отрефакторен в вызов приватного
   `renderCheckoutPage()`.** Не было в изначальном плане Scope как
   отдельный пункт, но `store()` при ошибках валидации должен
   показать ту же форму (товары/итог/блокировку/`checkout_token`) с
   `old`/`errors` — без общего метода это дублировало бы ~30 строк
   `index()` дважды в одном файле.
3. **Ручная проверка отката транзакции (DoD: «сбой на вставке
   order_items → откат») выполнена не «порчей SQL» дословно, а через
   FK-нарушение.** У БД на `mikhail700.beget.tech` пустой `sql_mode`
   (не `STRICT_TRANS_TABLES`) — переполнение `VARCHAR` и запись
   нечислового значения в `INT`-колонку молча коэрсятся вместо ошибки,
   поэтому «порча» через некорректные данные не падает. FK-проверки
   InnoDB работают независимо от `sql_mode` — временный скрипт в
   scratchpad (не в репозитории) повторил ровно ту же
   `INSERT orders` → `INSERT order_items` структуру, что и
   `createOrder()`, со намеренно несуществующим `product_variant_id`
   на шаге `order_items`: `orders`-строка создавалась, `order_items`
   падал с `SQLSTATE 23000`, `rollBack()` — `orders` не содержит
   строки после отката. Подтверждает механизм отката именно на этой
   БД, не только по коду.

## Out of scope — не трогаем

- Реальная оплата картой, `/payment/stub` — Фаза 3
- Создание `reserves` при оформлении — по `modules/stock.md` резерв
  закрепляется в момент **получения предоплаты**, не оформления
  заказа; здесь только повторная проверка уже существующего активного
  резерва
- `src/Core/OrderStatus.php`, `transitionOrderStatus()`,
  `cancelOrder()` — Таск 5
- UI отмены заказа, Панель менеджера — Фаза 4
- Email Менеджеру о новом заказе — ТЗ не требует (зафиксировано в «Вне
  скоупа фазы»)

## Definition of Done

- [x] Заказ в БД: `status='new'`, `payment_status='unpaid'`,
      `shipping_cost IS NULL`, `total` = Σ(цена из `product_variants`
      × qty), `comment` сохранён; `order_items` — снэпшот; изменение
      цены после заказа не меняет `order_items.price`/`orders.total`
      (цена перечитывается только на момент создания, `order_items`
      не пересчитывается задним числом)
- [x] Гость: `user_id IS NULL`, `guest_name`/`guest_phone` заполнены,
      `guest_email` NULL если не указан; Покупатель: `user_id`,
      `guest_*` NULL; самовывоз → `delivery_address IS NULL`, доставка
      → адрес сохранён — все 4 комбинации подтверждены `curl`
- [x] Двойной клик/повторный `POST` с тем же `checkout_token` → один
      заказ в БД, редирект на ту же страницу успеха; 6-й `POST` за
      минуту → блокировка, заказ не создан — подтверждено (5 попыток
      без блокировки, 6-я — `HTTP 302` на `/checkout` с флеш-сообщением
      «Слишком много попыток», без нового заказа в БД)
- [x] Подмена цены/`total`/`variant_id` в POST не влияет — `store()` не
      читает ни одно из этих полей из `input()` (проверено по коду —
      единственные читаемые поля: `checkout_token`, `name`, `phone`,
      `email`, `fulfillment_method`, `delivery_address`, `comment`,
      `payment_method`, `create_account`, `password`), состав и цены —
      только из серверной корзины и `product_variants`
- [x] Ошибки валидации: пустое обязательное поле (включая
      «Комментарий») → форма не отправлена, поле подсвечено, введённые
      значения сохранены; «Создать аккаунт» без пароля → ошибка, с
      паролем → пользователь создан и вошёл (`regenerateSession()`),
      заказ с `user_id`, гостевая корзина слита
- [x] Гость оформил с email → зарегистрировался тем же email →
      `orders.user_id` проставлен задним числом, `guest_*` очищены;
      другой гостевой заказ с иным/пустым email не тронут — подтверждено
      `curl` с временным тестовым аккаунтом
- [x] `/checkout/success` без `last_order_id` в сессии → редирект на
      `/`; чужой заказ по id в URL недоступен (маршрут не принимает id)
- [x] Сбой при вставке `order_items` → откат, в `orders` строки нет,
      запись в `storage/logs/app.log` (глобальный `set_exception_handler`
      в `ErrorHandlers.php` логирует с контекстом и файлом/строкой),
      пользователю — дружелюбное сообщение (`renderErrorResponse()`);
      подтверждено на этой БД через FK-нарушение (см. «Отклонения от
      плана», п.3) — `sql_mode` пустой, порча данными не падает
- [x] После успешного оформления корзина пуста, счётчик в шапке — 0 —
      подтверждено (`clearCart()`+`refreshCartCount()` после
      `createOrder()`)
- [x] Проверить `.docs/dod-global.md` — CSRF на обеих формах
      (`csrfField()`+`requireCsrf()`), вывод через `e()`/`formatPrice()`,
      `storage/logs/app.log` без новых ошибок после исправления
      найденного бага (п.1 «Отклонения от плана»); визуальная проверка
      вёрстки страницы «Заказ принят» на 320px+ в браузере не
      проводилась — в этой среде нет браузера, только структурная
      проверка `curl`-ом

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
