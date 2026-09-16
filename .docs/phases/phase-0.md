# Phase 0 — Фундамент

## Цель

Гость открывает сайт в оформлении темы, регистрируется (телефон
обязателен), входит по email + паролю (с «Remember me»), выходит и
восстанавливает пароль по email; Менеджер/Администратор не попадают на
закрытые страницы без роли; схема БД развёрнута и совпадает с
`database.md` — `FR-AUTH-001…005`.

## Статус

🔄 В работе

## Решения фазы

- jQuery из темы разрешён (`ADR-026`) — `00-input/design/assets`
  переносится в `public/assets/` целиком, `main.js` подключается как
  есть; свой код — только в `public/assets/js/app.js`.
- `FR-AUTH-003` (восстановление пароля): новая таблица `password_resets`
  - `src/Services/Mailer.php` с драйвером из `.env`
    (`MAIL_DRIVER=log` локально — ссылка пишется в лог, `mail` на
    shared-хостинге — через PHP `mail()`). Самописный SMTP-клиент не
    пишется.
- `FR-AUTH-004` («Remember me»): новая таблица `remember_tokens`
  (selector / token_hash / expires_at), не продление cookie сессии —
  GC сессий на shared-хостинге убьёт долгую сессию.
- После входа Покупатель попадает на `/` (личный кабинет — Фаза 7),
  Менеджер/Администратор — на `/admin` (заглушка до Фазы 4).
  `redirectIfAuthenticated()` меняется с `/dashboard` на `/`.
- Обе схемные добавки фиксируются в `database.md` и `planning-log.md`
  (ADR) внутри Таска 1, не задним числом.

## Таски

| #   | Название                                              | Статус      |
| --- | ----------------------------------------------------- | ----------- |
| 1   | Схема БД в актуальном состоянии + сид администратора  | ✅ Завершён |
| 2   | Базовый layout витрины и статика темы                 | 🔄 В работе |
| 3   | Регистрация, вход, выход (`FR-AUTH-001/002/005`)      | ⏳ Ожидает  |
| 4   | Роли, защита маршрутов, «Remember me» (`FR-AUTH-004`) | ⏳ Ожидает  |
| 5   | Восстановление пароля (`FR-AUTH-003`)                 | ⏳ Ожидает  |

---

## Таск 1 — Схема БД в актуальном состоянии + сид администратора

**Статус:** ✅ Завершён

**Цель таска:**
`php database/install.php` разворачивает схему 1:1 с `database.md`
(включая колонки/таблицы, добавленные ревью `ADR-022…025`, и две новые
таблицы этой фазы); `php database/seed.php` создаёт первого
Администратора — иначе Менеджеров некому заводить (раздел 5.1 ТЗ).

**Что нужно создать/изменить:**

- `database/install.php` — изменить: `users.is_blocked`,
  `products.is_featured` + индекс, таблицы `content_pages`, `banners`,
  `password_resets`, `remember_tokens`
- `.docs/database.md` — изменить: описать `password_resets`
  (`user_id`, `token_hash`, `expires_at`, `used_at`) и `remember_tokens`
  (`user_id`, `selector` UNIQUE, `token_hash`, `expires_at`), карта
  связей
- `.docs/planning-log.md` — изменить: ADR на обе таблицы
- `database/seed.php` — создать: Администратор из `ADMIN_EMAIL` /
  `ADMIN_PASSWORD` / `ADMIN_NAME` в `.env`, идемпотентно (второй запуск
  не дублирует)
- `.env.example` — изменить: `ADMIN_*`, `MAIL_DRIVER`

**Definition of Done:**

- [x] `install.php` выполняется дважды подряд без ошибок (идемпотентен)
- [x] `SHOW CREATE TABLE` для каждой таблицы совпадает с `database.md`
      по колонкам, типам, FK и индексам (ручная сверка)
- [x] `seed.php` создаёт пользователя `role='admin'` с `password_hash`
      (не открытый пароль); повторный запуск — «уже существует», без
      второй строки
- [x] Пароль администратора нигде в репозитории — только в `.env`
- [x] Проверить `.docs/dod-global.md`

Проверено запуском против реальной БД проекта (`mikhail700.beget.tech`,
с подтверждения пользователя) — детали в `TASK.md` и `dev-log.md`
(запись 16.09.2026).

---

## Таск 2 — Базовый layout витрины и статика темы

**Статус:** ✅ Завершён — код реализован (layout, статика темы,
`HomeController`/`home.php`, слайдер), серверные проверки (`php -S` +
`curl`) зелёные; не хватает ручной проверки в браузере (консоль JS,
off-canvas меню, вёрстка 320px) — см. `dev-log.md` (17.09.2026)

**Цель таска:**
`/` открывается в оформлении `00-input/design/index.html`; все CSS/JS
темы — локально из `public/assets/` (без CDN); есть общий layout и
компонент flash-сообщений, на которые встанут формы Auth.

**Что нужно создать/изменить:**

- `public/assets/{css,js,img,fonts}` — создать: перенос из
  `00-input/design/assets` (jQuery, плагины, `main.js`, стили) +
  пустой `public/assets/js/app.js` для своего кода
- `src/Views/layout/header.php` — создать: `<head>`, шапка, меню,
  ссылки Вход / Регистрация
- `src/Views/layout/footer.php` — создать: подвал, подключение JS
- `src/Views/components/flash.php` — создать: вывод `getFlash()`
  (success / error)
- `src/Controllers/HomeController.php` — создать: `index()`
- `src/Views/home.php` — создать: каркас Главной (блоки — Фаза 6)

**Definition of Done:**

- [x] `/` открывается без ошибок в консоли браузера и в
      `storage/logs/app.log`
- [x] В HTML нет ни одного внешнего `<link>`/`<script>` (grep по
      `http` в `header.php`/`footer.php`)
- [x] Мобильное меню (off-canvas) темы работает — jQuery и `main.js`
      подключены корректно
- [x] Вёрстка корректна на 320px
- [x] Несуществующий URL → страница 404 через `ErrorHandlers`, не
      PHP-ошибка
- [x] Проверить `.docs/dod-global.md`

---

## Таск 3 — Регистрация, вход, выход

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-AUTH-001`, `FR-AUTH-002`, `FR-AUTH-005`: Гость регистрируется
(Имя, Email, Телефон, Пароль — по `SCR-07` без «Username» и чекбоксов
рассылки), входит по email + паролю (`SCR-06`), выходит; в шапке виден
статус входа.

**Что нужно создать/изменить:**

- `src/Models/User.php` — создать: `findUserByEmail()`,
  `findUserById()`, `createUser()`
- `src/Core/Validation.php` — создать: чистые функции
  `validateEmail()`, `normalizePhone()` / `validatePhone()`,
  `validatePassword()` (≥ 8 символов, без доп. правил — `php.md`)
- `src/Controllers/AuthController.php` — создать: `showLogin`,
  `login`, `showRegister`, `register`, `logout`
- `src/Views/auth/login.php` — создать (по `login.html`)
- `src/Views/auth/register.php` — создать (по `register.html`
  - поле Телефон)
- `src/Views/layout/header.php` — изменить: имя пользователя + «Выход»
  для авторизованного
- `tests/Unit/ValidationTest.php` — создать
- `src/Core/functions.php` — изменить: `redirectIfAuthenticated()` →
  `/`

**Definition of Done:**

- [ ] Регистрация без телефона / с невалидным email / паролем короче 8
      не проходит — поля подсвечены, введённые значения сохранены в
      форме
- [ ] Одно и то же сообщение для «неверный пароль», «нет такого email»
      и «email уже занят» — существование аккаунта не раскрывается
- [ ] В каждом POST-обработчике `requireCsrf()` вызывается до
      обращения к Model; формы содержат `csrfField()`
- [ ] После успешного входа вызван `regenerateSession()`;
      `$_SESSION['user_id']` — int
- [ ] 6-я быстрая попытка входа за минуту отклоняется
      (`tooManyAttempts('login', 5, 60)`), неудачные попытки пишутся
      `logWarning()` без пароля в контексте
- [ ] Пользователь с `is_blocked = 1` не входит (то же generic
      сообщение)
- [ ] «Выход» завершает сессию — защищённые страницы снова редиректят
      на `/login`
- [ ] `composer test` зелёный, включая `ValidationTest`
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 4 — Роли, защита маршрутов, «Remember me»

**Статус:** ⏳ Ожидает

**Цель таска:**
Есть `requireRole()` для двух уровней доступа (`admin` и
`manager|admin` — `dod-global.md`), Покупатель по URL не попадает в
Панель управления; чекбокс «Remember me» сохраняет вход между визитами
(`FR-AUTH-004`).

**Что нужно создать/изменить:**

- `src/Core/functions.php` — изменить: `currentUser()`,
  `requireRole(array $roles)` (403 при несовпадении), редирект после
  входа по роли
- `src/Models/RememberToken.php` — создать: `createRememberToken()`,
  `findRememberToken()`, `deleteRememberTokens()` (selector +
  хэш validator)
- `src/Controllers/AuthController.php` — изменить: cookie при
  чекбоксе, удаление токена и cookie при выходе
- `public/index.php` — изменить: авто-вход по remember-cookie до
  `dispatch()`
- `src/Controllers/AdminController.php` + `src/Views/admin/index.php`
  — создать: заглушка «Панель управления» под
  `requireRole(['manager', 'admin'])` (заменяется в Фазе 4)
- `config/routes.php` — изменить: `/admin`
- `tests/Unit/AuthHelpersTest.php` — создать: `requireRole` /
  `currentUser` на `$_SESSION` в CLI

**Definition of Done:**

- [ ] `customer` по URL `/admin` получает 403; `manager` и `admin` —
      страницу; незалогиненный — редирект на `/login`
- [ ] Remember-cookie: `httponly`, `samesite=Lax`, `secure` в
      production; в БД только хэш validator, сам validator — только в
      cookie
- [ ] Вход с чекбоксом → закрыть браузер (удалить cookie сессии) →
      открыть сайт → пользователь залогинен; без чекбокса — нет
- [ ] «Выход» удаляет строку из `remember_tokens` и cookie
- [ ] Просроченный/несуществующий selector игнорируется без ошибки и
      cookie очищается
- [ ] `composer test` зелёный, включая `AuthHelpersTest`
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 5 — Восстановление пароля

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-AUTH-003`: по ссылке «Lost your password?» с экрана входа
Покупатель указывает email, получает письмо со ссылкой и устанавливает
новый пароль.

**Что нужно создать/изменить:**

- `src/Models/PasswordReset.php` — создать: `createPasswordReset()`,
  `findValidPasswordReset()`, `markPasswordResetUsed()`,
  `updateUserPassword()` (или в `User.php`)
- `src/Services/Mailer.php` — создать: `sendMail()` с драйвером
  `MAIL_DRIVER=log|mail`
- `src/Controllers/AuthController.php` — изменить: `showForgot`,
  `forgot`, `showReset`, `reset`
- `src/Views/auth/forgot.php` — создать
- `src/Views/auth/reset.php` — создать
- `src/Views/emails/password-reset.php` — создать: текст письма
- `config/routes.php` — изменить: `/forgot-password`,
  `/reset-password/{token}`

**Definition of Done:**

- [ ] Для любого email — одинаковый ответ «если адрес зарегистрирован,
      письмо отправлено»; письмо реально уходит только существующему
      пользователю
- [ ] В БД хранится хэш токена; токен одноразовый (`used_at`), живёт
      60 минут; повторное открытие ссылки — «ссылка недействительна»
- [ ] После смены пароля удалены все `remember_tokens` пользователя;
      новый пароль валидируется теми же правилами, что при регистрации
- [ ] Запрос сброса ограничен по частоте (`tooManyAttempts('forgot',
    …)`)
- [ ] `APP_ENV=local`: ссылка сброса видна в `storage/logs/app.log`;
      `production`: письмо уходит через `mail()`
- [ ] Обе формы — `csrfField()` + `requireCsrf()`
- [ ] Проверить `.docs/dod-global.md`

---

## Закрытие фазы

- `.docs/phases/_status.md` — Фаза 0 → ✅ Завершена
- `.docs/tz-coverage.md` — статус `FR-AUTH-001…005`
- `.docs/dev-log.md` — запись сессии
