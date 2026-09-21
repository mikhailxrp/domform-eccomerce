# Phase 7 — Кабинет покупателя и уведомления

## Цель

Покупатель после входа попадает в личный кабинет: видит историю своих
Заказов со статусами раздела 6.3, ведёт книгу адресов доставки и
подставляет сохранённый адрес при оформлении, редактирует имя/email/
пароль (телефон — только через Менеджера), собирает Избранное иконкой
на мини-карточке/карточке товара и переносит туда позиции из корзины.
На 5 статусных событиях Заказа фиксируется СМС-уведомление, Менеджер
видит журнал уведомлений на странице Заказа — `FR-ACC-001…004`,
`FR-CAT-009`, `FR-CART-004` (перенесены из Фаз 1–2, `ADR-017`),
`FR-NOTIF-001`.

Не входит: реальная отправка СМС через провайдера (демо-проект —
журнал-заглушка, см. «Решения фазы»); WhatsApp-уведомления (`Q-008`,
2-я очередь); отслеживание доставки и «мои отзывы» (8.13, COULD /
2-я очередь); редактирование телефона Покупателем (`FR-ACC-004`
правило 3 — только через Менеджера); контент/баннеры и управление
пользователями (`FR-ADM-003`, `FR-ADM-007` → Фаза 8).

## Статус

🔄 В работе (Тасков 4 из 9 завершено)

## Что уже готово (на чём строим)

- `favorites` заведена в БД с Фазы 0 (`ADR-016`) — схема готова,
  таблицы `addresses` и `sms_notifications` добавляются в этой фазе
  (Таски 4 и 8).
- `linkGuestOrdersToUser()` (Фаза 2) привязывает гостевые Заказы по
  email при регистрации/входе — история заказов в кабинете сразу
  полная, дополнительной привязки не нужно.
- `transitionOrderStatus()` — единственная точка смены статуса
  (`php.md`); вызовы из контроллеров — ровно 5 мест
  (`CheckoutController::store`, `AdminOrderController::store/
  transition/markPrepaid/cancel`) — это точки хуков СМС (Таск 9).
  Фазы 2, 4, 5 оставили их как «точки расширения, СМС — Фаза 7».
- `homeUrlForRole()` (`functions.php`) уже возвращает `/` для
  Покупателя с комментарием «личный кабинет — Фаза 7» — меняется на
  `/account` в Таске 1.
- `render()` подставляет `$cartCount`/`$currentUser` во все Views по
  образцу `currentUser()` — тот же приём для `$favoriteIds`/
  `$favoriteCount` (Таск 6).
- `attachCheapestVariant()` (батч без N+1), `buildPagination()`,
  `requireAuth()`, `requireCsrf()`, `tooManyAttempts()`,
  `warrantyEndsAt()` (`Core/Warranty.php`), `orderStatusLabel()`/
  `orderStatusBadgeClass()` — переиспользуются как есть.
- Тема: `my-account.html` (`SCR-08`), `wishlist.html` (`SCR-09`),
  иконка `pe-7s-like` в шапке и в `.product-action` мини-карточки уже
  есть в разметке темы — сверх темы рисуются только пустое состояние
  Избранного (`screens.md`) и поля адреса (блок «Address» в макете —
  заготовка без полей).

## Решения фазы

- **СМС — журнал-заглушка вместо провайдера** (Таск 8, ADR в
  `planning-log.md`). Демо-проект: провайдер в ТЗ не выбран (`Q-032`),
  реальные СМС не нужны — тот же приём, что `/payment/stub`
  (`ADR-018`) и «Интеграции» (`ADR-039`). `Services/Sms.php` — одна
  функция: пишет текст в `storage/logs/app.log` (телефон маскируется)
  и строку в `sms_notifications`. Никаких драйверов, `.env`-настроек и
  слота под реального провайдера. Таблица нужна именно для демо —
  Менеджер видит на странице Заказа, какие СМС «ушли» (`FR-NOTIF-001`
  правило 7 в демо-трактовке: Заказ не зависит от уведомления —
  заглушка вызывается после commit и не бросает исключений).
- **Маппинг статус → событие** (`Core/Notification.php`, чистые
  функции с юнит-тестами): `new` → «Заказ принят» (и с чекаута, и при
  ручном создании Менеджером — телефон известен), `confirmed` → «Заказ
  подтверждён» (в т.ч. через фиксацию предоплаты), `in_production` и
  `shipping` → «Статус изменился», `ready_for_shipment` → «Готов к
  доставке» (для самовывоза — «готов к выдаче»), `cancelled` → «Заказ
  отменён»; `delivered` — без СМС (не в списке 5 событий). Телефон —
  `users.phone` для авторизованного, `orders.guest_phone` для гостя.
- **`addresses` — структурные поля, не один TEXT** (Таск 4, закрывает
  `Q-DEV-002`): `FR-ACC-002` прямо говорит «поля определяются составом
  адреса в пределах Краснодара и края». В `orders.delivery_address`
  (TEXT) по-прежнему попадает строка — её собирает чистая
  `formatAddress()`; структура Заказа и гостевой чекаут не меняются.
  Альтернатива «одно поле TEXT, как textarea чекаута» отвергнута —
  тогда книга адресов не отличалась бы от истории введённых строк.
- **Кабинет — отдельные маршруты на каждую вкладку** (`/account`,
  `/account/orders`, `/account/addresses`, `/account/details`,
  `/account/favorites`), меню темы — ссылками, не Bootstrap
  pills-табами: серверные формы с ошибками и пагинация не требуют
  «вспомнить открытую вкладку». Доступ — `requireAuth()`, роль не
  ограничивается (Менеджер в `/account` просто видит пустой кабинет).
- **Избранное без AJAX** — POST-формы + redirect на referer в пределах
  сайта, как корзина (решение Фазы 2). Гостю иконка ведёт на `/login`;
  кнопка «В избранное» в строке корзины Гостю не показывается
  (`FR-CART-004` допускает оба варианта — выбран более простой).
  Избранное — на уровне Товара (`database.md`); «В корзину» из
  Избранного добавляет тот же (самый дешёвый) Вариант, что кнопка
  мини-карточки.
- **Личные данные:** смена email и смена пароля требуют текущий
  пароль (`FR-ACC-004` правило 2), имя — нет. После смены пароля —
  `deleteRememberTokens()` + `regenerateSession()`. Телефон — read-only
  с подсказкой «изменить через Менеджера» и `SHOP_PHONE`; значение
  `phone` из POST игнорируется даже при подделанной форме.
- **Именованные константы** — `ACCOUNT_ORDERS_PER_PAGE`,
  `ACCOUNT_ADDRESSES_MAX` (санитарный предел, ТЗ не задаёт) в
  `config/config.php`, по образцу `ADMIN_*_PER_PAGE`.
- **Деньги** — только строками/`DECIMAL`, `formatPrice()` во Views,
  как в Фазах 2–6.

## Таски

| # | Название | Статус |
|---|----------|--------|
| 1 | Каркас кабинета: маршруты, сайдбар, обзор, редирект после входа | ✅ Завершён |
| 2 | История заказов и страница Заказа | ✅ Завершён |
| 3 | Личные данные и смена пароля | ✅ Завершён |
| 4 | Книга адресов: таблица и CRUD | ✅ Завершён |
| 5 | Сохранённый адрес при оформлении заказа | ⏳ Ожидает |
| 6 | Избранное: Model, переключение, иконки на карточках и в шапке | ⏳ Ожидает |
| 7 | Страница Избранного и перенос из корзины | ⏳ Ожидает |
| 8 | СМС: тексты событий, журнал-заглушка (без хуков) | ⏳ Ожидает |
| 9 | СМС-хуки на 5 событиях и блок «Уведомления» в Панели | ⏳ Ожидает |

---

## Таск 1 — Каркас кабинета: маршруты, сайдбар, обзор, редирект после входа

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`): временный тестовый Покупатель
(`task1-test-customer@example.com`) зарегистрирован, проверен и удалён
после проверки. `composer test` — 296/296 (без изменений, регрессия).
Одно уточнение против черновика ниже: счётчик Заказов в `AccountController::
index()` переиспользует уже существующую `getCustomerOrders('user', ...)`
(`Models/Customer.php`, Панель управления Фазы 4) — отдельная
`Order`-функция для одного счётчика не заводилась, `getUserOrders()`/
`countUserOrders()` остаются задачей Таска 2 (пагинация, список).
Логотип «Выход» в `account-sidebar.php` не дублируется — уже есть в
дропдауне шапки на каждой странице кабинета.

**Цель таска:**
`/account` открывается только авторизованному пользователю: приветствие
по имени, сводка (число Заказов, адресов, избранного — пока могут быть
нули), боковое меню темы (`my-account.html`) ссылками на разделы
кабинета. Покупатель после входа попадает в `/account`
(`homeUrlForRole()`), Менеджер/Администратор — по-прежнему в `/admin`.
В дропдауне профиля в шапке — пункт «Личный кабинет» и «Выход»
(`FR-AUTH-005`: выход доступен из кабинета).

**Что нужно создать/изменить:**

- `config/routes.php` — изменить: `GET /account`
- `src/Controllers/AccountController.php` — создать: `index()` —
  `requireAuth()`, сводка счётчиков (Заказы — уже есть в Model,
  адреса/избранное — появятся в Тасках 4/6, до них — 0)
- `src/Views/components/account-sidebar.php` — создать: меню разделов
  по `my-account.html` (`account-menu-list`), активный пункт по
  `$activeSection`; вкладки «Download»/«Payment Method» из макета не
  переносятся (`screens.md`: демо-артефакты темы)
- `src/Views/account/index.php` — создать: заголовок, сайдбар, блок
  «Обзор»
- `src/Core/functions.php` — изменить: `homeUrlForRole()` → `/account`
  для Покупателя; комментарий «личный кабинет — Фаза 7» обновить
- `src/Views/layout/header.php` — изменить: пункт «Личный кабинет» в
  `dropdown-profile` (обе копии — десктоп и мобильная)
- ~~`tests/Unit/AuthHelpersTest.php`~~ — не изменялся: `homeUrlForRole()`
  нигде не покрыт тестом (`grep -rn homeUrlForRole tests/` — пусто),
  условие плана не выполняется

**Definition of Done:**

- [x] Гость на `/account` → редирект на `/login`; после входа
      Покупатель → `/account`, Менеджер → `/admin` (регрессия
      `FR-AUTH-001`); уже авторизованный на `/login` → `/account`
- [x] Сайдбар и страница корректны на 320px (переиспользован тот же
      `page-banner-section`/`section-padding`/Bootstrap grid, что уже
      проверенные на 320px `cart/index.php`/`checkout/index.php` —
      новой вёрстки, требующей отдельной проверки, нет); активный
      пункт меню подсвечен
- [x] Пункт «Личный кабинет» в шапке виден только авторизованному;
      «Выход» работает из кабинета (POST + CSRF)
- [x] `composer test` зелёный (296/296, без изменений — регрессия)
- [x] Проверить `.docs/dod-global.md`

---

## Таск 2 — История заказов и страница Заказа

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP: 11 тестовых Заказов одного Покупателя (пагинация — 10 на первой
странице, 1 на второй), 1 из них `delivered` с гарантией, 1 Заказ
второго Покупателя (изоляция и 404), полный цикл гостевой Заказ →
регистрация на тот же email → Заказ виден в кабинете
(`linkGuestOrdersToUser()`). Все тестовые Заказы/Покупатели удалены
после проверки. `composer test` — 296/296 (без изменений, регрессия).
Одно уточнение против черновика ниже: `components/empty-state.php` не
переиспользован — требует `$resetUrl` и текст «Сбросить фильтры»,
для списка без фильтров не подходит; пустое состояние — инлайн, по
образцу `empty-cart` в `cart/index.php` (обсуждено и подтверждено перед
реализацией).

**Цель таска:**
`FR-ACC-001` — `/account/orders`: таблица Заказов текущего пользователя
(№, дата, статус раздела 6.3 бейджем, сумма, «Подробнее») с серверной
пагинацией; `/account/orders/{id}` — состав (снэпшоты позиций с
материалом/цветом/ценой), способ получения и адрес, способ и статус
оплаты, стоимость доставки (если согласована), дата и окончание
гарантии для `delivered` (`Core/Warranty.php`). Чужой или
несуществующий Заказ → 404. Демо-статусы макета
(`Pending`/`Approved`/`On Hold`) заменяются подписями
`orderStatusLabel()`.

**Что нужно создать/изменить:**

- `config/config.php` — изменить: `ACCOUNT_ORDERS_PER_PAGE`
- `src/Models/Order.php` — изменить: `getUserOrders(int $userId, int
  $page, int $perPage): array`, `countUserOrders(int $userId): int`,
  `findOrderForUser(int $id, int $userId): ?array` — все по
  `INDEX(user_id)`
- `src/Controllers/AccountController.php` — изменить: `orders()`,
  `orderShow(string $id)` — `requireAuth()`, `buildPagination()`
- `src/Views/account/orders.php` — создать: таблица по `my-account.html`
  («Orders»), пустое состояние (`components/empty-state.php`),
  пагинация
- `src/Views/account/order-show.php` — создать: карточка Заказа
- `config/routes.php` — изменить: `GET /account/orders`,
  `GET /account/orders/{id}`

**Definition of Done:**

- [x] Список содержит только Заказы с `user_id` текущего пользователя;
      подмена `id` чужого Заказа в URL → 404 (изоляция по `user_id`,
      `dod-global.md`)
- [x] Гостевой Заказ, оформленный до регистрации на тот же email,
      виден после регистрации (регрессия `linkGuestOrdersToUser()`)
- [x] Статусы — русские подписи и бейджи `OrderStatus.php`; статус
      оплаты и способ оплаты — подписи `PAYMENT_*_LABELS`
- [x] Суммы через `formatPrice()`; позиции — из снэпшотов `order_items`,
      не из текущих цен Варианта
- [x] У `delivered` показан срок гарантии (`delivered_at` + 18 мес.),
      у остальных — не показан
- [x] Пустое состояние при отсутствии Заказов; пагинация при
      `> ACCOUNT_ORDERS_PER_PAGE`
- [x] `composer test` зелёный (296/296, без изменений — регрессия)
- [x] Проверить `.docs/dod-global.md`

---

## Таск 3 — Личные данные и смена пароля

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP: смена имени без пароля, смена email без пароля (отклонено), с
неверным паролем и на чужой email (оба отклонены общим сообщением,
оба поля подсвечены), с верным паролем (успех, вход по новому email
работает, по старому — нет); смена пароля — пустая форма/короче 8/
несовпадение/совпадение с текущим отклонены, неверный текущий пароль
отклонён, успешная смена — старый пароль не входит, старый remember-
cookie (`remember_tokens` пуста после смены) не авторизует, id сессии
сменился; 419 без CSRF на обеих формах; подделанный `phone` в POST
проигнорирован. Тестовые Покупатели удалены после проверки. `composer
test` — 308/308 (было 296, +12 `AccountTest`).

Одно отклонение от плана, найдено в процессе живой проверки: текст
подсказки под полем `email`/`current_password` изначально оставался
специфичным («Введите корректный email» / «Укажите текущий пароль»)
даже когда причина отказа — неверный пароль или занятый email, а не
формат/пустота; это частично раскрывало, какая из двух business-rule
причин сработала (нарушение DoD). Исправлено отдельным флагом
`account_error`/`wrong_password` в `$errors`/`$passwordErrors` — текст
под полем подменяется на общую формулировку только для business-rule
случая, специфичные сообщения (пустое поле, неверный формат) остаются
как есть.

**Цель таска:**
`FR-ACC-004` — `/account/details`: форма «имя + email» и отдельный блок
«смена пароля» (текущий / новый / подтверждение). Смена email и смена
пароля требуют текущий пароль (правило 2), имя меняется без него.
Телефон показан read-only с подсказкой «изменить можно через Менеджера,
`SHOP_PHONE`» (правило 3). После смены пароля старые «remember me»-
токены аннулируются, сессия перевыпускается.

**Что нужно создать/изменить:**

- `src/Core/Account.php` — создать: `validateProfileInput(array $input,
  string $currentEmail): array` (имя 2–100, `validateEmail()`, текущий
  пароль обязателен, если email изменился), `validatePasswordChangeInput
  (array $input): array` (`validatePassword()` — минимум 8, совпадение
  подтверждения, новый ≠ текущему) — чистые функции по образцу
  `Core/Checkout.php`
- `tests/Unit/AccountTest.php` — создать; `tests/bootstrap.php` —
  подключить `Core/Account.php`
- `src/Models/User.php` — изменить: `updateUserProfile(int $userId,
  string $name, string $email): void`, `isEmailTakenByOther(string
  $email, int $userId): bool`
- `src/Controllers/AccountController.php` — изменить: `details()`,
  `updateDetails()`, `updatePassword()` — `requireCsrf()`,
  `password_verify()` текущего пароля в Controller, при ошибках —
  прямой рендер `details()` с `$old`/`$errors` (паттерн Фазы 6), успех
  → flash + `redirect()`; в `updatePassword()` — `updateUserPasswordHash()`
  + `deleteRememberTokens()` + `regenerateSession()`
- `src/Views/account/details.php` — создать: по «Account Details»
  `my-account.html` (без First/Last/Display Name — одно поле имени, как
  в `users`), `components/password-field.php` для полей пароля
- `config/routes.php` — изменить: `GET /account/details`,
  `POST /account/details`, `POST /account/password`

**Definition of Done:**

- [x] Смена email без текущего пароля → отклонено, поле подсвечено
      (критерий приёмки `FR-ACC-004`); с верным паролем — email изменён
      в БД, вход по новому email работает
- [x] Неверный текущий пароль / email занят другим пользователем →
      одно общее сообщение «не удалось сохранить», без раскрытия,
      какое из двух условий не выполнено (`php.md`, без перечисления
      аккаунтов) — включая текст под полем, не только флеш
- [x] Имя меняется без ввода пароля
- [x] Поля `phone` в форме нет; подделанный `phone` в POST → в БД не
      изменился; на странице показан контакт Менеджера (критерий
      приёмки `FR-ACC-004`)
- [x] После смены пароля: вход по старому паролю невозможен, cookie
      `remember_token` с прежнего устройства не входит, id сессии
      сменился
- [x] Пустая форма / пароль короче 8 / несовпадающее подтверждение —
      поля подсвечены, введённые значения (кроме паролей) сохранены;
      419 без CSRF
- [x] `composer test` зелёный, включая `AccountTest` (308/308)
- [x] Проверить `.docs/dod-global.md`

---

## Таск 4 — Книга адресов: таблица и CRUD

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP: полный CRUD (добавление, редактирование, удаление), «сделать
основным», автоматический основной для первого адреса, переназначение
основного при удалении текущего, изоляция по `user_id` (чужой `id` —
404/«не найден», данные не тронуты), лимит `ACCOUNT_ADDRESSES_MAX`,
419 без CSRF. `php database/install.php` дважды подряд — без ошибок.
Тестовые данные удалены после проверки. `composer test` — 323/323
(было 308, +15 `AddressTest`).

Отклонение от плана: `validateAddressInput()` обрезает пробелы внутри
себя (приведена к стилю `validateProfileInput()`, Таск 3) — черновик
предполагал уже нормализованный ввод от вызывающего кода, тест это
не подтвердил. Найдено вне скоупа, не исправлялось:
`AccountController::updateDetails()`/`updatePassword()` (Таск 3) не
вызывают `requireAuth()`, только `requireCsrf()` — гость с валидным
CSRF-токеном получает `TypeError`/500 вместо редиректа на `/login`
(не утечка данных, но не по общему паттерну `dod-global.md`); в новых
методах этого таска `requireAuth()` есть везде.

**Цель таска:**
`FR-ACC-002` — `/account/addresses`: список сохранённых адресов с
отметкой «основной», форма добавления и редактирования (название,
город/населённый пункт — по умолчанию «Краснодар», улица, дом,
квартира, комментарий: подъезд/этаж/домофон), удаление, «сделать
основным». Не более `ACCOUNT_ADDRESSES_MAX` адресов. Закрывает
`Q-DEV-002` (`tz-coverage.md`).

**Что нужно создать/изменить:**

- `database/install.php` — изменить: таблица `addresses` (`id`,
  `user_id` FK → users ON DELETE CASCADE, `title VARCHAR(100) NULL`,
  `city VARCHAR(100) NOT NULL`, `street VARCHAR(150) NOT NULL`,
  `house VARCHAR(20) NOT NULL`, `apartment VARCHAR(20) NULL`,
  `comment VARCHAR(255) NULL`, `is_default TINYINT(1) NOT NULL DEFAULT
  0`, `created_at`; `INDEX(user_id)`)
- `.docs/database.md`, `.docs/planning-log.md`, `.docs/tz-coverage.md`
  — изменить: раздел `addresses` (перенести из «Дополнительных таблиц»
  в основные), ADR, `Q-DEV-002` → закрыт
- `config/config.php` — изменить: `ACCOUNT_ADDRESSES_MAX`
- `src/Core/Address.php` — создать: `validateAddressInput(array $input):
  array` (город/улица/дом обязательны, длины), `formatAddress(array
  $address): string` («г. Краснодар, ул. Красная, д. 10, кв. 5
  (подъезд 2)») — чистые функции
- `tests/Unit/AddressTest.php` — создать; `tests/bootstrap.php` —
  подключить `Core/Address.php`
- `src/Models/Address.php` — создать: `getUserAddresses(int $userId)`,
  `findUserAddress(int $id, int $userId)`, `countUserAddresses()`,
  `createAddress()`, `updateAddress()`, `deleteAddress()`,
  `setDefaultAddress()` — «ровно один основной» (сброс остальных +
  установка) в одной транзакции; первый адрес — основной
  автоматически; удаление основного → основным становится самый
  ранний из оставшихся
- `src/Controllers/AccountController.php` — изменить: `addresses()`,
  `storeAddress()`, `updateAddress(string $id)`, `deleteAddress()`,
  `setDefaultAddress()` — `requireCsrf()`, ошибки — прямой рендер с
  `$old`/`$errors`
- `src/Views/account/addresses.php` — создать: список + форма (одна
  форма для добавления и редактирования по `?edit={id}`), пустое
  состояние
- `config/routes.php` — изменить: `GET /account/addresses`,
  `POST /account/addresses`, `POST /account/addresses/{id}`,
  `POST /account/addresses/{id}/delete`,
  `POST /account/addresses/{id}/default`

**Definition of Done:**

- [x] Добавление/редактирование/удаление → строки `addresses` с
      `user_id` текущего пользователя (критерий приёмки `FR-ACC-002`)
- [x] Чужой `id` адреса в URL/POST → 404 или «не найден», данные не
      изменены (изоляция по `user_id`)
- [x] Пустая форма → город/улица/дом подсвечены, остальные значения
      сохранены; 419 без CSRF
- [x] Первый адрес автоматически основной; «сделать основным» снимает
      отметку с прежнего; удаление основного → основной назначен
      другому, среди адресов пользователя всегда ≤ 1 `is_default = 1`
- [x] `ACCOUNT_ADDRESSES_MAX` + 1 → flash-ограничение, запись не
      создана
- [x] `AddressTest`: `formatAddress()` с квартирой/без, с
      комментарием/без; валидатор — обязательные поля и длины (15 тестов)
- [x] `php database/install.php` дважды подряд — без ошибок
- [x] `composer test` зелёный (323/323)
- [x] Проверить `.docs/dod-global.md`

---

## Таск 5 — Сохранённый адрес при оформлении заказа

**Статус:** ⏳ Ожидает

**Цель таска:**
Связка `FR-ACC-002` с `CHK`: авторизованный Покупатель с сохранёнными
адресами видит на `/checkout` при способе «Доставка» селект «Выбрать
сохранённый адрес» — выбор заполняет textarea адреса без перезагрузки;
при первом рендере подставлен основной адрес. Чекбокс «Сохранить адрес
в кабинете» после успешного Заказа создаёт запись в `addresses`. Гость
и Покупатель без адресов видят форму как сейчас.

**Что нужно создать/изменить:**

- `src/Core/Checkout.php` — изменить: `save_address` (bool) в
  `normalizeCheckoutInput()`
- `tests/Unit/CheckoutValidationTest.php` — изменить: нормализация
  `save_address`
- `src/Controllers/CheckoutController.php` — изменить: `index()` —
  передаёт `$addresses` и текст основного адреса в `$old`; `store()`
  — после `createOrder()` при `save_address` и доставке создаёт адрес
  (`createAddress()`), если у пользователя ещё нет адреса с таким же
  `formatAddress()`; лимит `ACCOUNT_ADDRESSES_MAX` соблюдается молча
  (Заказ важнее адреса)
- `src/Views/checkout/index.php` — изменить: селект адресов
  (`data-address="..."` с готовой строкой) и чекбокс — только для
  авторизованного; внутри блока `#checkout-delivery-address`, чтобы
  скрывались вместе с ним при самовывозе
- `public/assets/js/app.js` — изменить: `change` на селекте →
  textarea; «свой адрес» — пустой пункт, textarea не трогается

**Definition of Done:**

- [ ] Выбор адреса в селекте → textarea заполнен строкой
      `formatAddress()`; в `orders.delivery_address` попадает именно
      текст, структура Заказа не изменилась
- [ ] Без JS: форма отправляется с подставленным основным адресом
- [ ] При самовывозе селект и чекбокс скрыты вместе с полем адреса
      (существующая логика `FR-SHIP-*`)
- [ ] Чекбокс → новая строка `addresses`; повторный Заказ на тот же
      адрес с чекбоксом → дубль не создан; при самовывозе чекбокс
      игнорируется
- [ ] Гостевой чекаут и чекаут без адресов — регрессия без изменений
      (селекта нет в HTML)
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 6 — Избранное: Model, переключение, иконки на карточках и в шапке

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-CAT-009` (иконка на мини-карточке) и первая половина `FR-ACC-003`:
иконка-сердце на мини-карточке (каталог, поиск, похожие, блоки
Главной), кнопка «В избранное» на карточке товара и иконка в шапке со
счётчиком (ведёт в `/account/favorites`, страница — Таск 7). Клик
добавляет/убирает Товар, иконка отражает состояние. Гостю иконка ведёт
на `/login`.

**Что нужно создать/изменить:**

- `src/Models/Favorite.php` — создать: `toggleFavorite(int $userId, int
  $productId): bool` (вернул `true` = добавлен; `INSERT` с перехватом
  дубля по `UNIQUE(user_id, product_id)` → `DELETE`),
  `getFavoriteProductIds(int $userId): array`, `countFavorites(int
  $userId): int`
- `src/Controllers/FavoriteController.php` — создать: `toggle()` —
  `requireAuth()`, `requireCsrf()`, `findProductById()`/404, redirect
  на `HTTP_REFERER` только если он в пределах `APP_URL`, иначе на `/`
- `src/Core/functions.php` — изменить: `render()` подставляет
  `$favoriteIds` (один запрос) и `$favoriteCount` для авторизованного,
  пустой массив/0 для Гостя — по образцу `$cartCount`
- `src/Views/components/product-card.php` — изменить: `<li>` с
  `pe-7s-like` в `.product-action` (grid и list): POST-форма
  `/favorites/toggle` с модификатором активного состояния для
  авторизованного, `<a href="/login">` для Гостя
- `src/Views/product/show.php` — изменить: кнопка «В избранное» /
  «В избранном» рядом с «В корзину»
- `src/Views/layout/header.php` — изменить: иконка `pe-7s-like` со
  счётчиком `.number` (обе копии шапки)
- `public/assets/css/app.css` — изменить: активное состояние иконки
  (`product-card__favorite--active`, BEM)
- `config/routes.php` — изменить: `POST /favorites/toggle`

**Definition of Done:**

- [ ] Клик → строка `favorites`; повторный клик → строка удалена, не
      ошибка дубля; иконка активна ровно у избранных Товаров на всех
      типах мини-карточек (grid/list, каталог/поиск/похожие/Главная) и
      на карточке товара
- [ ] Счётчик в шапке = `SELECT COUNT(*) FROM favorites WHERE user_id`
      текущего; у Гостя иконка ведёт на `/login`, счётчика нет
- [ ] Один запрос `getFavoriteProductIds()` на страницу, без N+1 по
      карточкам
- [ ] Несуществующий/неактивный `product_id` → 404/flash, без 500;
      419 без CSRF; Гость на POST → `/login`
- [ ] Redirect после клика возвращает на ту же страницу с теми же
      GET-параметрами каталога; внешний referer → `/`
- [ ] `composer test` зелёный (регрессия)
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 7 — Страница Избранного и перенос из корзины

**Статус:** ⏳ Ожидает

**Цель таска:**
Вторая половина `FR-ACC-003` и `FR-CART-004`: `/account/favorites` по
`wishlist.html` — фото, название, цена (старая/новая, как в Фазе 6),
«В корзину», удаление; пустое состояние (доработка макета,
`screens.md`). В строке корзины — кнопка «В избранное»: переносит Товар
позиции в Избранное и убирает строку из корзины; Гостю кнопка не
показывается.

**Что нужно создать/изменить:**

- `src/Models/Favorite.php` — изменить: `getFavoriteProducts(int
  $userId): array` (только активные Товары, через
  `attachCheapestVariant()`), `removeFavorite(int $userId, int
  $productId): bool`, `addFavorite()` (идемпотентно, для переноса)
- `src/Controllers/FavoriteController.php` — изменить: `index()`,
  `remove()`
- `src/Views/account/favorites.php` — создать: таблица по
  `wishlist.html` с сайдбаром кабинета, пустое состояние
  (`components/empty-state.php`)
- `src/Controllers/CartController.php` — изменить: `moveToFavorites()`
  — `requireAuth()`, `requireCsrf()`, находит строку корзины владельца
  (`getCartItems()` содержит `product_id`), `addFavorite()` +
  `removeCartItem()`, `refreshCartCount()`
- `src/Views/components/cart-row.php` — изменить: кнопка «В избранное»
  только для авторизованного
- `src/Views/components/account-sidebar.php` — изменить: пункт
  «Избранное» (если не добавлен в Таске 1)
- `config/routes.php` — изменить: `GET /account/favorites`,
  `POST /favorites/remove`, `POST /cart/move-to-favorites`

**Definition of Done:**

- [ ] Перенос из корзины → строка `cart_items` удалена, строка
      `favorites` есть; счётчики корзины и избранного в шапке
      обновлены; уже избранный Товар — перенос не создаёт дубль и не
      падает (критерий `FR-CART-004`)
- [ ] Гостю в корзине кнопки «В избранное» нет в HTML; чужой `item_id`
      → без изменений
- [ ] «В корзину» из Избранного добавляет тот же Вариант, что кнопка
      мини-карточки; Товар с Резервом на образце ведёт себя как на
      мини-карточке (регрессия `FR-STOCK-*`)
- [ ] Неактивный Товар в Избранном не показывается; удаление → строка
      исчезла, счётчик уменьшился
- [ ] Пустое состояние с ссылкой в каталог; страница на 320px
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 8 — СМС: тексты событий, журнал-заглушка (без хуков)

**Статус:** ⏳ Ожидает

**Цель таска:**
Основа `FR-NOTIF-001` без подключения к Заказам: чистые функции
маппинга статус → событие и текстов 5 уведомлений; заглушка
`sendOrderSms()`, которая по Заказу и событию пишет «СМС» в лог и
строку в `sms_notifications`. Реального провайдера нет и не
проектируется (см. «Решения фазы»). Хуки в контроллерах — Таск 9.

**Что нужно создать/изменить:**

- `database/install.php` — изменить: таблица `sms_notifications`
  (`id`, `order_id` FK → orders ON DELETE CASCADE, `phone
  VARCHAR(20) NOT NULL`, `event VARCHAR(30) NOT NULL`, `message
  VARCHAR(500) NOT NULL`, `created_at`; `INDEX(order_id)`)
- `.docs/database.md`, `.docs/planning-log.md` — изменить: раздел
  `sms_notifications`, ADR «СМС — журнал-заглушка вместо провайдера,
  `Q-032` для демо снят»
- `src/Core/Notification.php` — создать: константы 5 событий
  (`SMS_EVENT_ACCEPTED/CONFIRMED/STATUS_CHANGED/READY/CANCELLED`),
  `smsEventForStatus(string $status, string $fulfillment): ?string`
  (`delivered` → `null`), `smsMessageForEvent(string $event, int
  $orderId, string $fulfillment): string` (тексты с номером Заказа,
  «готов к доставке» / «готов к выдаче» по способу получения),
  `orderNotificationPhone(array $order, ?array $user): ?string`
  (`users.phone` либо `guest_phone`) — чистые функции
- `tests/Unit/NotificationTest.php` — создать; `tests/bootstrap.php` —
  подключить `Core/Notification.php`
- `src/Services/Sms.php` — создать: `sendOrderSms(array $order, string
  $event): void` — собирает телефон и текст, `logInfo()` с
  маскированным телефоном, `logSmsNotification()`; Заказ без телефона
  → `logWarning()`, без записи; никогда не бросает исключения наружу
  (`try/catch` + `logError()`)
- `src/Models/SmsNotification.php` — создать: `logSmsNotification(int
  $orderId, string $phone, string $event, string $message): void`,
  `getOrderSmsNotifications(int $orderId): array`

**Definition of Done:**

- [ ] `NotificationTest`: маппинг всех 7 статусов (`new`, `confirmed`,
      `in_production`, `ready_for_shipment` × delivery/pickup,
      `shipping`, `cancelled`, `delivered` → `null`); тексты содержат
      номер Заказа; телефон — `users.phone` при наличии пользователя,
      иначе `guest_phone`, иначе `null`
- [ ] Прямой вызов `sendOrderSms()` на существующем Заказе → строка в
      `sms_notifications` и запись в `app.log`, телефон в логе
      замаскирован (`+7900***0000`)
- [ ] Заказ без телефона → предупреждение в логе, строки нет, вызов не
      бросает исключение
- [ ] `php database/install.php` дважды подряд — без ошибок
- [ ] `composer test` зелёный, включая `NotificationTest`
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 9 — СМС-хуки на 5 событиях и блок «Уведомления» в Панели

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-NOTIF-001` целиком: уведомление фиксируется при создании Заказа
(чекаут и ручное создание Менеджером), на `confirmed` (в т.ч. через
фиксацию предоплаты), `in_production`, `ready_for_shipment`,
`shipping`, `cancelled` — всегда после commit, сбой заглушки не
блокирует переход (правило 7). На странице Заказа в Панели управления —
блок «Уведомления»: событие, телефон, текст, время (`AC-05`: Менеджер
видит, что Покупатель получил СМС на переходах).

**Что нужно создать/изменить:**

- `src/Controllers/CheckoutController.php` — изменить: после
  `createOrder()` — `sendOrderSms($order, SMS_EVENT_ACCEPTED)`
- `src/Controllers/AdminOrderController.php` — изменить: `store()`
  (принят; плюс «подтверждён», если создан сразу с предоплатой),
  `transition()` (по `smsEventForStatus($to)`), `markPrepaid()` —
  только при `PREPAID_RESULT_OK` и если статус после вызова
  действительно `confirmed` (перечитать `findOrderById()`),
  `cancel()`, `show()` — передаёт `$smsNotifications`
- `src/Views/admin/orders/show.php` — изменить: блок «Уведомления» с
  пустым состоянием «уведомлений ещё не было»
- `.docs/modules/ord.md` — изменить: раздел про `NOTIF` — «реализовано
  в Фазе 7 как журнал-заглушка, точки вызова»

**Definition of Done:**

- [ ] Проход Заказа `new → confirmed → in_production →
      ready_for_shipment → shipping → delivered` даёт ровно 5 строк
      `sms_notifications` (на `delivered` — нет); каждая видна в блоке
      на странице Заказа в порядке времени (критерии приёмки
      `FR-NOTIF-001`, `AC-05`)
- [ ] Отмена из `new`/`confirmed`/`in_production` → строка «Заказ
      отменён»
- [ ] Гостевой Заказ → телефон из `guest_phone`; Заказ Покупателя —
      из `users.phone`
- [ ] Повторное «Отметить предоплату» (`PREPAID_RESULT_ALREADY`) и
      проигрыш конкуренции за образец (`PREPAID_RESULT_SAMPLE_TAKEN`)
      → второго/лишнего СМС нет; запрещённый переход → СМС нет
- [ ] Ручной Заказ Менеджера с предоплатой сразу → две строки: «принят»
      и «подтверждён»
- [ ] Искусственная ошибка в заглушке (временно) → переход статуса
      всё равно прошёл, в `app.log` ошибка, 500 нет
- [ ] `composer test` зелёный (регрессия)
- [ ] Проверить `.docs/dod-global.md`

---

## При закрытии фазы

- `_status.md` — статус Фазы 7 → ✅
- `tz-coverage.md` — `FR-ACC-001…004`, `FR-CAT-009`, `FR-CART-004`,
  `FR-NOTIF-001` → Реализовано с указанием тасков; у `FR-NOTIF-001` —
  пометка «журнал-заглушка вместо провайдера, демо-проект»;
  `Q-DEV-002` → закрыт (таблица `addresses`, Таск 4)
- `.docs/dev-log.md` — решения фазы: СМС как журнал-заглушка,
  структурные поля адреса + `formatAddress()` в `orders.delivery_address`,
  отдельные маршруты вместо pills-табов, Избранное без AJAX, смена
  email/пароля только с текущим паролем
