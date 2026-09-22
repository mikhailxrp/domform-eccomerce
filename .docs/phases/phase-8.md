# Phase 8 — Админ-панель (контент и доступ) и статические страницы

## Цель

Посетитель открывает 7 статических страниц — Контакты с формой
обратного звонка, О компании, Шоурум с картой, Доставка и оплата,
Возврат и гарантия, Публичная оферта, Политика обработки ПДн — по
живым ссылкам шапки и футера. Менеджер и Администратор редактируют
тексты/фото этих страниц и баннеры слайдера Главной в Панели
управления без разработчика; Менеджер видит заявки на обратный звонок
там же, где Заказы. Только Администратор создаёт и блокирует учётные
записи Менеджеров и меняет реквизиты магазина в разделе «Настройки» —
Менеджер этих разделов не видит и по прямому URL не открывает —
`FR-ADM-003`, `FR-ADM-007`, `FR-CNT-001…006`.

Не входит: блог (`SCR-12`/`SCR-13`, COULD / 2-я очередь); конструктор
прав (`FR-ADM-006` — закрывается отсутствием кода); смена роли/пароля
другого сотрудника Администратором (ТЗ просит только создание и
блокировку, пароль Менеджер сбрасывает сам через `/forgot-password`);
хранение секретов/ключей интеграций в БД (`Q-032`: секреты остаются в
`.env`, раздел «Настройки» — только реквизиты магазина, см. «Решения
фазы»); принудительное завершение активной сессии при блокировке
(`FR-ADM-007` правило 3 — «при следующей попытке входа»); ИИ
(`FR-AI-*` → Фаза 9).

## Статус

🔄 В работе (4 из 8 тасков завершено)

## Что уже готово (на чём строим)

- Таблицы `content_pages` (`ADR-022`) и `banners` (`ADR-023`) созданы в
  `database/install.php`; у `banners` есть сид из 3 слайдов и
  `getActiveBanners()` (Таск 6 Фазы 6), Главная уже читает слайдер из
  БД. У `content_pages` — ни строк, ни Model, ни маршрутов.
- `users.is_blocked` (`ADR-025`) уже блокирует вход
  (`AuthController::login()`) и авто-вход по remember-cookie
  (`attemptRememberLogin()` в `functions.php`) — ровно то, что требует
  правило 3 `FR-ADM-007`. Не хватает только UI создания/блокировки.
- В шапке (`header.php`) и футере (`footer.php`) ссылки «О компании»,
  «Доставка и оплата», «Контакты», «Оплата», «Возврат и гарантия»,
  «Условия использования» — `href="#"`.
- Контакты магазина — константы `SHOP_PHONE`, `SHOP_PHONE_TEL`,
  `SHOP_WHATSAPP_URL` в `config/config.php`, используются в 6 местах
  (`footer.php`, `checkout/index.php`, `payment/stub.php`,
  `account/details.php`).
- Сайдбар Панели — массив `$adminNavItems` в
  `layout/admin-header.php`; бейдж (число `pending` отзывов) уже
  реализован — тот же приём для заявок на звонок.
- `requireRole(['admin'])` ещё ни разу не использовался — все
  `/admin/*` пока `['manager','admin']`. Эта фаза первая вводит
  admin-only маршруты; `Q-DEV-001` предупреждает: проверять
  `role = 'admin'` явно, не полагаясь на формулировки `dod-global.md`.
- `storeProductImage()`/`deleteStoredFile()` (`Services/FileUpload.php`)
  и `validateUploadedImage()` (`Core/Upload.php`) — готовый безопасный
  аплоад; `public/uploads/.htaccess` уже запрещает исполнение PHP.
- `tooManyAttempts()`, `csrfField()`/`requireCsrf()`,
  `buildPagination()`, `setFlash()`, паттерн «при ошибке валидации —
  прямой рендер с `$old`/`$errors`» (Фаза 6) — переиспользуются как
  есть.
- Макеты: `00-input/design/about.html` (`SCR-11`) для «О компании»;
  `00-input/admin/userlist.html` + `editprofile.html` для сотрудников;
  `blog-post.html`/`mail-compose.html` — для формы редактирования
  текста (`Q-DEV-007`). Страниц Контакты/Шоурум/Доставка/Возврат/
  Юридические в теме нет — дорисовываются в стиле темы (`Q-012`).

## Решения фазы

- **Формат `content_pages.body` — плоский текст с мини-разметкой, не
  HTML** (Таск 1, ADR в `planning-log.md`). Пустая строка = абзац,
  строка `## ` = подзаголовок, `- ` = пункт списка; больше ничего.
  Рендер — чистая `renderContentBody()` (`Core/Content.php`) с
  юнит-тестами, каждый фрагмент проходит через `e()`. Это ровно «текст
  и фото», которыми ограничивает редактирование правило 3
  `FR-ADM-003`. Альтернатива — HTML от Менеджера через `strip_tags` с
  whitelist — отвергнута: самописный санитайзер — риск XSS, а
  Менеджер по `php.md` такой же «внешний ввод», как Покупатель.
- **Форма обратного звонка пишет в таблицу `callback_requests`**
  (Таск 4, ADR), Менеджер видит заявки в разделе «Заявки» Панели с
  бейджем новых (Таск 5). ТЗ (`FR-CNT-001`) не говорит, куда попадает
  заявка; таблица — в духе демо-раздела «Каналы продаж» (`ADR-039`):
  заявка с сайта видна там же, где Заказы. Альтернатива — только
  письмо через готовый `sendMail()` — отвергнута: на shared-хостинге
  `mail()` ненадёжен и в демо невидим.
- **«Технические настройки» (`FR-ADM-007` п. 4, `Q-DEV-008`) — в
  минимальном составе: реквизиты магазина в key-value таблице
  `settings`** (Таск 2, ADR). Телефон, WhatsApp, email, адрес цеха,
  режим работы, URL встраиваемой карты — то, что нужно страницам этой
  фазы и что сегодня лежит константами в `config.php` (смена телефона
  в футере = «обращение к разработчику», против духа `FR-ADM-003`).
  Плюс read-only блок «О системе» (`APP_ENV`, версии PHP/MySQL, размер
  `app.log`). Секреты и ключи интеграций в БД **не** переезжают —
  остаются в `.env` (`Q-032`, `CLAUDE.md`). `SHOP_*` константы
  удаляются, единственный источник — `setting()` с кэшем на запрос
  (один `SELECT` на страницу).
- **Карта шоурума — `<iframe>` конструктора Яндекс.Карт** (Таск 3):
  провайдер выбран ТЗ (`FR-CNT-003`, примечание), это единственная
  внешняя зависимость витрины — правило «никакого CDN» (`general.md`)
  относится к CSS/JS, не к явно заказанному виджету. URL — в
  `settings` (`map_embed_url`); пустое значение → карта скрыта, под
  картой всегда текстовый адрес и кнопки «Позвонить»/«WhatsApp», чтобы
  страница была полезна и без iframe.
- **Блок команды на «О компании» — статично во View** (Таск 3):
  состав задан ТЗ явно (Волков С. И., Волкова А. вместо 3 демо-людей
  темы, `FR-CNT-002`), это структура страницы, а не содержимое
  (правило 3 `FR-ADM-003`). Текст и фото страницы — из
  `content_pages.about`, редактируются.
- **Сотрудники: только создание и блокировка** (Таск 8): создать
  (имя, email, пароль ≥ 8 символов, роль `manager`/`admin`, телефон
  необязателен — `database.md`, `users.phone`), заблокировать/
  разблокировать. Себя заблокировать нельзя. При блокировке —
  `deleteRememberTokens()`, чтобы remember-cookie не восстановила
  сессию. Сообщение при входе заблокированного — общее (`php.md`: не
  раскрывать причину отказа).
- **Публичные URL:** `/about`, `/contacts`, `/showroom` — отдельные
  маршруты (у них свои View и логика), остальные четыре текстовые
  страницы — одним маршрутом `/pages/{slug}` с whitelist slug
  (`CONTENT_PAGE_SLUGS`), неизвестный slug → 404.
- **Загруженные изображения контента и баннеров — в
  `public/uploads/content/`** (`UPLOAD_CONTENT_DIR`), отдельная
  `storeContentImage()` рядом с `storeProductImage()`, без рефакторинга
  последней; `deleteStoredFile()` расширяется на второй префикс. Сиды
  баннеров темы (`assets/images/slider/...`, без ведущего `/`) и
  загруженные (`/uploads/content/...`) сосуществуют — `home.php` уже
  нормализует ведущий слэш.
- **Именованные константы** — `ADMIN_CALLBACKS_PER_PAGE`,
  `UPLOAD_CONTENT_DIR` в `config/config.php`, по образцу
  `ADMIN_REVIEWS_PER_PAGE`/`UPLOAD_PRODUCTS_DIR`.

## Таски

| # | Название | Статус |
|---|----------|--------|
| 1 | Статические страницы: Model, рендер текста, сид, маршрут, ссылки | ✅ Завершён |
| 2 | Настройки магазина: таблица `settings`, `setting()`, раздел «Настройки» (admin-only) | ✅ Завершён |
| 3 | «О компании» (SCR-11) и «Шоурум с картой» | ✅ Завершён |
| 4 | «Контакты» и форма обратного звонка | ✅ Завершён |
| 5 | Заявки на звонок в Панели управления | ⏳ Ожидает |
| 6 | Редактирование текстов страниц в Панели | ⏳ Ожидает |
| 7 | Управление баннерами слайдера | ⏳ Ожидает |
| 8 | Сотрудники: создание и блокировка Менеджеров (admin-only) | ⏳ Ожидает |

Порядок — по зависимостям: сначала публичные страницы и реквизиты
(их используют остальные), потом редактирование в Панели, потом
доступ.

---

## Таск 1 — Статические страницы: Model, рендер текста, сид, маршрут, ссылки

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`): сид идемпотентен (7 строк после двух
запусков, ручная правка `title` сохранена), 7 slug → 200, неизвестные/
искажённые slug → 404, экранирование проверено временной подменой тела
строки (откачено). `composer test` — 350/350 (+13 `ContentTest`).
Подробности — `.docs/dev-log.md` 21.09.2026. Одно уточнение против
черновика ниже: в `header.php` заменены обе копии ссылок — десктопное
и мобильное меню (вторая копия в плане явно не упоминалась).

**Цель таска:**
`FR-CNT-004`, `FR-CNT-005`, `FR-CNT-006` открываются по
`/pages/{slug}` с текстом из `content_pages`; остальные четыре страницы
(`about`, `contacts`, `showroom` — свои View в Тасках 3–4) пока тоже
рендерятся общим шаблоном. Все `href="#"` на страницы CNT в шапке и
футере становятся живыми ссылками.

**Что нужно создать/изменить:**
- `src/Core/Content.php` — создать: `CONTENT_PAGE_SLUGS` (7 slug →
  заголовок по умолчанию: `contacts`, `about`, `showroom`,
  `delivery-payment`, `return-warranty`, `offer`, `privacy-policy`),
  `isContentPageSlug(string): bool`, `renderContentBody(string):
  string` (мини-разметка, см. «Решения фазы») — чистые функции без БД
- `tests/Unit/ContentTest.php` — создать; `tests/bootstrap.php` —
  подключить `Core/Content.php`
- `src/Models/ContentPage.php` — создать: `findContentPageBySlug(string):
  ?array`, `getAllContentPages(): array`
- `src/Controllers/PageController.php` — создать: `show(string $slug)`
  — slug вне whitelist или строки нет → 404
- `src/Views/pages/show.php` — создать: `page-banner-section` темы,
  хлебные крошки (`components/breadcrumbs.php`), заголовок,
  `image_path` (если есть), тело через `renderContentBody()`
- `database/install.php` — изменить: сид 7 строк `content_pages`
  через `INSERT IGNORE` (есть `UNIQUE(slug)`); стартовые тексты по ТЗ:
  доставка — самовывоз по записи и собственная доставка, оплата —
  картой на сайте/наличными/переводом, предоплата 30–50 %, остаток при
  получении (`FR-CNT-004`); возврат — только по звонку, целиком, без
  автоматического возврата денег, гарантия 18 месяцев (`FR-CNT-005`);
  типовые оферта и политика ПДн по 152-ФЗ (`FR-CNT-006`)
- `config/routes.php` — изменить: `GET /pages/{slug}`
- `src/Views/layout/header.php`, `src/Views/layout/footer.php` —
  изменить: живые ссылки («Оплата»/«Доставка» → `/pages/delivery-payment`,
  «Возврат и гарантия» → `/pages/return-warranty`, «Условия
  использования» → две ссылки: оферта и политика ПДн; «О компании»/
  «Контакты» пока на `/pages/about`, `/pages/contacts` — Таски 3–4
  переключат на свои маршруты)

**Definition of Done:**
- [x] `/pages/delivery-payment`, `/pages/return-warranty`,
      `/pages/offer`, `/pages/privacy-policy` — 200, заголовок и текст
      из БД; `/pages/nope` и `/pages/../etc` — 404, не 500
- [x] `renderContentBody()`: абзацы, `##`, списки рендерятся; строка
      `<script>alert(1)</script>` выводится как текст; пустое тело →
      пустая строка (юнит-тесты)
- [x] `php database/install.php` дважды подряд — ровно 7 строк
      `content_pages`, без дублей; существующий отредактированный текст
      повторным запуском не перезаписывается
- [x] В `header.php`/`footer.php` не осталось `href="#"` на страницы
      CNT; страница корректна на 320px
- [x] `composer test` зелёный
- [x] Проверить `.docs/dod-global.md`

---

## Таск 2 — Настройки магазина: таблица `settings`, `setting()`, раздел «Настройки»

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`, три временных пользователя ролей
`admin`/`manager`/`customer`, удалены после проверки): `admin` → 200,
`manager` → редирект `/admin` (без пункта в сайдбаре), `customer` →
`/account`, гость → `/login`; POST без CSRF → 419; валидное
обновление сразу видно в футере/БД (включая кириллицу — на этой
машине сама передача кириллицы через argv curl в Windows-консоли
рвёт байты, обошли телом запроса из файла, приложение ни при чём);
неизвестный ключ не пишется; невалидный телефон / не-`https` карта —
подсветка, БД не изменена; временным пробоем подтверждён ровно один
`SELECT` на страницу. `composer test` — 364/364 (+14 `SettingsTest`).
Реализовано по плану ниже без отклонений; `phoneToTel()` — тонкая
обёртка над уже существующим `normalizePhone()`, не новый regex.

**Цель таска:**
`FR-ADM-007` п. 1 и 4 в минимальном составе (см. «Решения фазы»).
`/admin/settings` открывается только `admin`: форма реквизитов
магазина (телефон, WhatsApp, email, адрес цеха, режим работы, URL
карты) + read-only блок «О системе». Менеджер пункта в сайдбаре не
видит, по прямому URL — редирект на `/admin` (`requireRole()`).
Реквизиты читаются из БД во всех местах, где были константы `SHOP_*`.

**Что нужно создать/изменить:**
- `database/install.php` — изменить: таблица `settings`
  (`key VARCHAR(60) NOT NULL UNIQUE`, `value TEXT NOT NULL`,
  `updated_at`), сид текущих значений констант через `INSERT IGNORE`;
  `.docs/database.md` — раздел `settings`; `.docs/planning-log.md` —
  ADR (реквизиты в БД, секреты остаются в `.env`)
- `src/Core/Settings.php` — создать: `SETTING_KEYS` (whitelist ключей
  с подписями), `setting(string $key): string` (читает
  `getAllSettings()` один раз на запрос), `phoneToTel(string): string`
  (`+7 900 000-00-00` → `+79000000000`), `validateSettingsInput(array):
  array` — чистые; `tests/Unit/SettingsTest.php` — создать;
  `tests/bootstrap.php` — подключить
- `src/Models/Setting.php` — создать: `getAllSettings(): array`,
  `updateSettings(array $values): void` (только ключи из
  `SETTING_KEYS`, транзакция)
- `src/Controllers/AdminSettingController.php` — создать: `index()`,
  `update()` — оба `requireRole(['admin'])`, POST — `requireCsrf()` →
  при ошибке прямой рендер с `$old`/`$errors`, при успехе `redirect()`
- `src/Views/admin/settings/index.php` — создать: форма реквизитов,
  блок «О системе» (`APP_ENV`, `PHP_VERSION`, версия MySQL, размер
  `storage/logs/app.log`, доступность записи в `public/uploads/`)
- `src/Views/layout/admin-header.php` — изменить: у элементов
  `$adminNavItems` появляется необязательный `roles`, массив
  фильтруется по `currentUser()['role']`; пункт «Настройки» с
  `roles => ['admin']`; `config/routes.php` — `GET/POST /admin/settings`
- `config/config.php` — удалить `SHOP_PHONE`, `SHOP_PHONE_TEL`,
  `SHOP_WHATSAPP_URL`; `src/Views/layout/footer.php`,
  `src/Views/checkout/index.php`, `src/Views/payment/stub.php`,
  `src/Views/account/details.php` — `setting('shop_phone')`,
  `phoneToTel(...)`, `setting('shop_whatsapp_url')`

**Definition of Done:**
- [x] `manager` на `/admin/settings` → редирект на `/admin`, пункта в
      сайдбаре нет; `admin` — форма и блок «О системе»; `customer` →
      редирект на `/account`; Гость → `/login`; POST без CSRF → 419
- [x] Смена телефона в форме → новое значение в футере, чекауте,
      заглушке оплаты и подсказке кабинета без деплоя; `tel:` собран
      из нового номера
- [x] Неизвестный ключ в POST игнорируется (не пишется в БД);
      невалидный телефон / не-`https` URL / пустое обязательное поле —
      поле подсвечено, БД не изменена
- [x] На любую страницу витрины — ровно один `SELECT ... FROM settings`
      (кэш на запрос); `grep -rn SHOP_ src/ config/` — пусто
- [x] `composer test` зелёный (новые тесты `phoneToTel()`,
      `validateSettingsInput()`)
- [x] Проверить `.docs/dod-global.md`

---

## Таск 3 — «О компании» (SCR-11) и «Шоурум с картой»

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`): `/about`/`/showroom` → 200, `/pages/about`/
`/pages/showroom` → 301 на новые адреса, остальные 5 `/pages/{slug}` и
неизвестный slug — без изменений (регрессия); карта скрывается при
пустом `map_embed_url` (секции нет в HTML) и появляется при заполненном
(проверено переключением значения в обе стороны, восстановлено).
`composer test` — 364/364 без изменений. Реализовано по плану ниже с
двумя уточнениями (согласованы с пользователем при `task-init`): вместо
новых классов `.team-card`/`.showroom-map`/`.showroom-notice` в
`app.css` реиспользованы готовые классы темы (`.single-team`,
`.checkout-info`/`.info-header`, `.checkout-call`) и Bootstrap-утилита
`.ratio.ratio-16x9` — `app.css` не тронут; редирект выбран 301 (не
404). Блок преимуществ на `/about` — тот же `.single-benefit`, что на
Главной (3 пункта), без отдельного пункта про гарантию.

**Цель таска:**
`FR-CNT-002` — `/about` по `about.html`: редактируемый текст и фото из
`content_pages.about`, статичный блок команды из 2 реальных людей
(Волков С. И. — владелец, Волкова А. — менеджер) вместо 3
демо-сотрудников темы. `FR-CNT-003` — `/showroom`: адрес цеха и режим
работы из `settings`, iframe Яндекс.Карт, явное пояснение «посещение
по записи, не свободный вход», кнопки «Позвонить» / «Написать в
WhatsApp».

**Что нужно создать/изменить:**
- `src/Controllers/PageController.php` — изменить: `about()`,
  `showroom()` (читают свою строку `content_pages` + `setting()`)
- `src/Views/pages/about.php` — создать: по `about.html` — вводный
  блок с текстом/фото страницы, блок преимуществ, блок команды
  (2 карточки)
- `src/Views/pages/showroom.php` — создать: адрес, режим работы,
  предупреждение «по записи», iframe карты (скрыт при пустом
  `map_embed_url`), кнопки связи, редактируемый текст страницы
- `public/assets/css/app.css` — изменить: `.team-card`,
  `.showroom-map` (адаптивный iframe 16:9), `.showroom-notice` — BEM,
  mobile-first, без inline-стилей
- `config/routes.php` — изменить: `GET /about`, `GET /showroom`;
  `src/Views/layout/header.php`, `footer.php` — ссылки «О компании» →
  `/about`, добавить «Шоурум» → `/showroom`

**Definition of Done:**
- [x] `/about`: заголовок, текст и фото из `content_pages.about`;
      блок команды — Волков С. И. и Волкова А., демо-имён темы в HTML нет
- [x] `/showroom`: карта загружается по URL из `settings`; при пустом
      `map_embed_url` секция карты отсутствует в HTML, адрес и кнопки
      на месте; «по записи» видно без прокрутки на 320px; iframe не
      создаёт горизонтальный скролл
- [x] `/pages/about` и `/pages/showroom` больше не отдают общий шаблон
      (редирект 301 на новые адреса или 404 — выбрать при `task-init`,
      зафиксировать в `dev-log.md`)
- [x] В HTML нет внешних адресов, кроме `src` iframe карты и `wa.me`
- [x] `composer test` зелёный (регрессия)
- [x] Проверить `.docs/dod-global.md`

---

## Таск 4 — «Контакты» и форма обратного звонка

**Статус:** ✅ Завершён 22.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`): `/contacts` → 200, реквизиты из `settings`,
текст из `content_pages.contacts`; `/pages/contacts` → 301 (то же
решение, что в Таске 3), остальные 5 `/pages/{slug}` и `/about`/
`/showroom` без регрессии; валидная форма → строка `status='new'` +
flash об успехе; невалидный телефон — подсветка, БД не тронута; 4-я
отправка за 10 минут заблокирована (ровно 3 строки, не 4); без CSRF →
419; авторизованный тестовый Покупатель видит имя/телефон
подставленными. Кириллица через `curl --data-urlencode` на этой машине
по-прежнему бьётся аргументами консоли (тот же артефакт, что в Таске 2)
— обойдено телом запроса из файла, приложение не при чём. Все тестовые
данные удалены после проверки. `composer test` — 375/375 (+11
`CallbackTest`). Реализовано по плану ниже без отклонений; визуальная
проверка 320px в браузере в этой сессии недоступна (нет браузера) —
подтверждена переиспользованием уже провизуально проверенных в Тасках
1–3 классов (`single-form`, `showroom-info-card`), см. `dev-log.md`
22.09.2026.

**Цель таска:**
`FR-CNT-001` — `/contacts`: адрес цеха, телефон, email, кнопка
WhatsApp (из `settings`), редактируемый текст из
`content_pages.contacts`, форма «Перезвоните мне» (имя, телефон,
комментарий). Отправка → строка в новой таблице `callback_requests`
со статусом `new` + flash «Мы перезвоним». Rate-limit по образцу
формы отзыва.

**Что нужно создать/изменить:**
- `database/install.php` — изменить: таблица `callback_requests`
  (`id`, `name VARCHAR(150)`, `phone VARCHAR(20)`, `comment VARCHAR(500)
  NULL`, `status ENUM('new','processed') DEFAULT 'new'`, `created_at`,
  `processed_at DATETIME NULL`, `INDEX(status)`); `.docs/database.md`
  — раздел `callback_requests`; `.docs/planning-log.md` — ADR
- `src/Core/Callback.php` — создать: `validateCallbackInput(array):
  array` (имя обязательно, телефон — тот же валидатор, что при
  регистрации, комментарий ≤ 500), `CALLBACK_STATUS_*` — чистые;
  `tests/Unit/CallbackTest.php` — создать; `tests/bootstrap.php` —
  подключить
- `src/Models/CallbackRequest.php` — создать: `createCallbackRequest(
  array $data): ?int`
- `src/Controllers/PageController.php` — изменить: `contacts()`,
  `storeCallback()` — `requireCsrf()`, `tooManyAttempts('callback', 3,
  600)` / `hitRateLimit('callback')`, при ошибке — прямой рендер
  `contacts` с `$old`/`$errors`, при успехе `setFlash()` +
  `redirect('/contacts')`; авторизованному Покупателю имя/телефон
  подставляются
- `src/Views/pages/contacts.php` — создать: блок реквизитов
  (адрес/телефон/email/WhatsApp), текст страницы, форма
  (`csrfField()`, подсветка ошибок)
- `config/routes.php` — изменить: `GET /contacts`,
  `POST /contacts/callback`; `header.php`/`footer.php` — «Контакты»
  → `/contacts`

**Definition of Done:**
- [ ] Валидная форма → строка в `callback_requests` (`status='new'`),
      flash об успехе, форма очищена; пустая/невалидная (телефон
      «123») — поля подсвечены, строки в БД нет
- [ ] 4-я отправка за 10 минут с одного клиента → отказ с сообщением,
      строки нет; без CSRF → 419
- [ ] Реквизиты на странице совпадают с `settings`; кнопка WhatsApp
      ведёт на `shop_whatsapp_url` с `rel="noopener"`
- [ ] `/pages/contacts` больше не отдаёт общий шаблон (то же решение,
      что в Таске 3); страница корректна на 320px
- [ ] `composer test` зелёный (новые тесты `validateCallbackInput()`)
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 5 — Заявки на звонок в Панели управления

**Статус:** ⏳ Ожидает

**Цель таска:**
Менеджер и Администратор видят `/admin/callbacks`: список заявок с
фильтром по статусу (по умолчанию `new`), серверной пагинацией,
кнопкой «Обработано»; в сайдбаре пункт «Заявки» с бейджем числа новых
— тот же паттерн, что бейдж отзывов на модерации.

**Что нужно создать/изменить:**
- `config/config.php` — изменить: `ADMIN_CALLBACKS_PER_PAGE`
- `src/Models/CallbackRequest.php` — изменить: `getAdminCallbacks(array
  $filters, int $page, int $perPage): array`, `countAdminCallbacks(array
  $filters): int`, `countNewCallbacks(): int`,
  `markCallbackProcessed(int $id): bool` (идемпотентно, с проверкой
  существования при `rowCount() === 0`, как `setReviewStatus()`)
- `src/Controllers/AdminCallbackController.php` — создать: `index()`
  (whitelist статуса через `CALLBACK_STATUS_*`, `buildPagination()`),
  `process()` — `requireRole(['manager','admin'])`, POST →
  `requireCsrf()` → `redirect()`
- `src/Views/admin/callbacks/index.php` — создать: фильтр, список
  (имя, телефон ссылкой `tel:`, комментарий, дата, статус, действие),
  пустое состояние, пагинация — по образцу `admin/reviews/index.php`
- `src/Views/layout/admin-header.php` — изменить: пункт «Заявки» с
  бейджем `countNewCallbacks()`; `config/routes.php` —
  `GET /admin/callbacks`, `POST /admin/callbacks/{id}/process`

**Definition of Done:**
- [ ] Заявка, отправленная с `/contacts`, появляется в списке `new`;
      «Обработано» → `status='processed'`, `processed_at` заполнен,
      бейдж в сайдбаре уменьшился на 1; повторное «Обработано» —
      идемпотентно, несуществующий id → flash «не найдена»
- [ ] Фильтр `new`/`processed`/все; неизвестное значение игнорируется,
      не 500; пагинация при > `ADMIN_CALLBACKS_PER_PAGE` строк
- [ ] Бейдж совпадает с `SELECT COUNT(*) FROM callback_requests WHERE
      status='new'`; при 0 — не показывается
- [ ] `customer` по `/admin/callbacks` → редирект; Гость → `/login`;
      419 без CSRF
- [ ] `composer test` зелёный (регрессия)
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 6 — Редактирование текстов страниц в Панели

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-ADM-003` п. 1, 3 — `/admin/content`: список 7 страниц (заголовок,
slug, «обновлено», ссылка «Открыть на сайте»);
`/admin/content/{slug}/edit`: заголовок, тело (textarea с подсказкой
мини-разметки), фото (загрузить / удалить). Критерий приёмки ТЗ:
изменён текст «О компании» → изменение видно на сайте без
разработчика.

**Что нужно создать/изменить:**
- `src/Core/Content.php` — изменить: `validateContentPageInput(array):
  array` (заголовок 1–200, тело непустое), `publicUrlForContentSlug(
  string): string` (`about` → `/about`, `contacts` → `/contacts`,
  `showroom` → `/showroom`, остальные → `/pages/{slug}`);
  `tests/Unit/ContentTest.php` — дополнить
- `config/config.php` — изменить: `UPLOAD_CONTENT_DIR`
  (`public/uploads/content`)
- `src/Services/FileUpload.php` — изменить: `storeContentImage(array
  $file): ?string` (тот же приём, что `storeProductImage()`, префикс
  `/uploads/content/`); `deleteStoredFile()` — разрешить второй префикс
  `uploads/content/`
- `src/Models/ContentPage.php` — изменить: `updateContentPage(string
  $slug, string $title, string $body): bool`, `updateContentPageImage(
  string $slug, ?string $path): void`
- `src/Controllers/AdminContentController.php` — создать: `index()`,
  `edit(string $slug)`, `update(string $slug)` (текст + необязательный
  файл), `deleteImage(string $slug)` — `requireRole(['manager','admin'])`
- `src/Views/admin/content/index.php`, `src/Views/admin/content/edit.php`
  — создать (паттерн формы — `blog-post.html`/`mail-compose.html`;
  подсказка по мини-разметке рядом с textarea; превью текущего фото)
- `src/Views/layout/admin-header.php` — изменить: пункт «Контент»;
  `config/routes.php` — `GET /admin/content`,
  `GET /admin/content/{slug}/edit`, `POST /admin/content/{slug}`,
  `POST /admin/content/{slug}/image/remove`

**Definition of Done:**
- [ ] Правка заголовка/текста «О компании» → `/about` показывает новое
      сразу (критерий приёмки `FR-ADM-003`); то же для `/pages/*`
- [ ] Загрузка `.php`, файла с поддельным расширением или > лимита
      отклонена (`validateUploadedImage()`), flash с ошибкой, файл на
      диске не появился; валидное фото → файл в
      `public/uploads/content/`, `image_path` обновлён, старый файл
      удалён; «Удалить фото» → файл стёрт, `image_path = NULL`
- [ ] Неизвестный slug на `/admin/content/{slug}/edit` → 404; пустой
      заголовок/тело — подсветка, БД не изменена
- [ ] `customer` → редирект; 419 без CSRF
- [ ] `composer test` зелёный (новые тесты `validateContentPageInput()`,
      `publicUrlForContentSlug()`)
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 7 — Управление баннерами слайдера

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-ADM-003` п. 2, `FR-HOME-001` — `/admin/banners`: список
(миниатюра, текст, ссылка, порядок, активен); создание и
редактирование с загрузкой изображения; включить/выключить показ.
Физического удаления нет — тот же паттерн `is_active`, что у Товаров
(`database.md`).

**Что нужно создать/изменить:**
- `src/Core/BannerForm.php` — создать: `validateBannerInput(array,
  bool $hasExistingImage): array` (текст ≤ 200, ссылка — относительная
  `/...` или `https://...`, `sort_order` — целое ≥ 0, изображение
  обязательно при создании) — чистая; `tests/Unit/BannerFormTest.php`
  — создать; `tests/bootstrap.php` — подключить
- `src/Models/Banner.php` — изменить: `getAllBanners(): array`
  (`ORDER BY sort_order`), `findBannerById(int): ?array`,
  `createBanner(array): ?int`, `updateBanner(int, array): bool`,
  `toggleBanner(int): bool`
- `src/Controllers/AdminBannerController.php` — создать: `index()`,
  `create()`, `store()`, `edit()`, `update()`, `toggle()` —
  `requireRole(['manager','admin'])`; загрузка через
  `storeContentImage()` из Таска 6
- `src/Views/admin/banners/index.php`, `src/Views/admin/banners/form.php`
  — создать (одна форма на создание и редактирование, как у категорий)
- `src/Views/layout/admin-header.php` — изменить: пункт «Баннеры»;
  `config/routes.php` — `GET /admin/banners`, `GET /admin/banners/create`,
  `GET /admin/banners/{id}/edit`, `POST /admin/banners`,
  `POST /admin/banners/{id}`, `POST /admin/banners/{id}/toggle`

**Definition of Done:**
- [ ] Новый баннер с загруженным фото появляется в слайдере Главной в
      позиции `sort_order`; «Выключить» → слайд исчез, строка осталась;
      «Включить» → вернулся. Сиды темы (`assets/images/slider/...`) и
      загруженные (`/uploads/content/...`) отображаются одновременно
- [ ] Создание без изображения — ошибка с подсветкой; редактирование
      без нового файла сохраняет старое изображение; замена файла
      удаляет прежний загруженный (файлы темы из `assets/` не трогаются)
- [ ] Ссылка `javascript:...` / `http://` отклонена валидатором;
      несуществующий id → 404 / flash
- [ ] `customer` → редирект; 419 без CSRF
- [ ] `composer test` зелёный (новые тесты `validateBannerInput()`)
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 8 — Сотрудники: создание и блокировка Менеджеров (admin-only)

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-ADM-007` п. 1–3 — `/admin/users` только для `admin`: список
пользователей с ролями `manager`/`admin` (имя, email, телефон, роль,
дата, бейдж «Заблокирован»); форма создания (имя, email, пароль ≥ 8,
роль, телефон необязателен); «Заблокировать» / «Разблокировать». Себя
заблокировать нельзя. Менеджер раздел не видит и не открывает.

**Что нужно создать/изменить:**
- `src/Core/StaffForm.php` — создать: `STAFF_ROLES`,
  `validateStaffInput(array): array` (пароль — то же правило ≥ 8, что
  регистрация, без доп. сложности — `php.md`; роль из `STAFF_ROLES`;
  телефон — валидатор регистрации, если непустой) — чистая;
  `tests/Unit/StaffFormTest.php` — создать; `tests/bootstrap.php` —
  подключить
- `src/Models/User.php` — изменить: `getStaffUsers(): array`
  (`WHERE role IN ('manager','admin') ORDER BY created_at`),
  `createStaffUser(array $data): ?int` (`password_hash()` — в
  контроллере, как при регистрации; `null` при дубликате email),
  `setUserBlocked(int $id, bool $blocked): bool`
- `src/Controllers/AdminUserController.php` — создать: `index()`,
  `store()`, `block()`, `unblock()` — все `requireRole(['admin'])`;
  `block()` отказывает, если `id === currentUser()['id']`, и вызывает
  `deleteRememberTokens($id)`; POST — `requireCsrf()`, при ошибке
  прямой рендер с `$old`/`$errors`, при успехе `redirect()`
- `src/Views/admin/users/index.php` — создать: по `userlist.html` —
  таблица сотрудников + форма создания (паттерн `admin/reviews/
  index.php`: список и форма на одной странице)
- `src/Views/layout/admin-header.php` — изменить: пункт «Сотрудники» с
  `roles => ['admin']`; `config/routes.php` — `GET /admin/users`,
  `POST /admin/users`, `POST /admin/users/{id}/block`,
  `POST /admin/users/{id}/unblock`

**Definition of Done:**
- [ ] Созданный Менеджер входит по выданным email/паролю и видит
      Панель без пунктов «Сотрудники»/«Настройки»; `/admin/users` и
      `/admin/settings` под ним → редирект на `/admin` (критерий
      приёмки `FR-ADM-007`)
- [ ] Заблокированный Менеджер: вход → общее сообщение ошибки
      (`AUTH_ERROR`, причина не раскрывается); remember-cookie не
      восстанавливает сессию, его строки `remember_tokens` удалены при
      блокировке; «Разблокировать» возвращает доступ (критерий приёмки
      `FR-ADM-007`)
- [ ] Блокировка собственной учётки → flash-отказ, `is_blocked` не
      изменился; дубликат email / пароль < 8 / роль вне `STAFF_ROLES`
      — подсветка, строки нет; телефон пустой — допускается
- [ ] `manager`/`customer` на `/admin/users` → редирект; Гость →
      `/login`; 419 без CSRF; Покупатели (`role='customer'`) в списке не
      показываются
- [ ] `composer test` зелёный (новые тесты `validateStaffInput()`)
- [ ] Проверить `.docs/dod-global.md`

---

## При закрытии фазы

- `_status.md` — статус Фазы 8 → ✅
- `tz-coverage.md` — `FR-ADM-003`, `FR-ADM-007`, `FR-CNT-001…006` →
  Реализовано с указанием тасков; `Q-DEV-007` закрыть (своя разметка
  `/admin/content` и `/admin/banners`, мини-разметка вместо HTML);
  `Q-DEV-008` закрыть с пометкой «в минимальном составе: реквизиты
  магазина в `settings`, секреты остаются в `.env`»; у `FR-HOME-001`
  снять пометку «редактирование — Фаза 8»
- `database.md` — разделы `settings`, `callback_requests`; у
  `content_pages.body` — уточнение формата (мини-разметка,
  `renderContentBody()`); карта связей без изменений (обе таблицы без
  FK)
- `planning-log.md` — ADR: мини-разметка вместо HTML, заявки на звонок
  в таблице, реквизиты магазина в `settings`
- `.docs/dev-log.md` — решения фазы и итог проверки на реальной БД
- `admin-assembly.md` — строки «Контент», «Баннеры», «Заявки»,
  «Настройки» (закрытие `Q-DEV-007`/`Q-DEV-008`)
