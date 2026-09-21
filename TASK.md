# Current Task

## Фаза
Phase 8 — Админ-панель (контент и доступ) и статические страницы
(`.docs/phases/phase-8.md`), Таск 2 из 8.

**Статус:** ✅ Завершён 21.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`, три временных пользователя ролей
`admin`/`manager`/`customer`, удалены после проверки): `install.php`
дважды → 6 строк без дублей, ручная правка сохранена; `admin` → 200 с
формой и «О системе», `manager` → редирект `/admin` без пункта в
сайдбаре, `customer` → `/account`, гость → `/login`; POST без CSRF →
419; валидное обновление сразу в футере/чекауте/заглушке
оплаты/кабинете и в БД (включая кириллицу — рвалась не в приложении, а
в передаче через argv curl в Windows-консоли, обошли телом запроса из
файла); неизвестный ключ не пишется; невалидный телефон / не-`https`
карта — подсветка, БД не изменена; временным пробоем подтверждён ровно
один `SELECT` на страницу. `composer test` — 364/364 (было 350, +14).
Реализовано по плану ниже без отклонений.

## Задача
`FR-ADM-007` п. 1 и 4 в минимальном составе: новая таблица `settings`
(key/value), функция `setting()` с кэшем на запрос, раздел
`/admin/settings` — форма реквизитов магазина (телефон, WhatsApp,
email, адрес цеха, режим работы, URL карты) + read-only блок
«О системе», доступен только `role = 'admin'` (первый admin-only
маршрут проекта, `Q-DEV-001`). Константы `SHOP_PHONE`/`SHOP_PHONE_TEL`/
`SHOP_WHATSAPP_URL` удаляются из `config.php`, 4 файла переключаются на
`setting()`.

## Что проверено в коде перед планом
- `config.php:41-43` — ровно 3 константы; подтверждено grep:
  используются в 4 файлах (`footer.php`, `checkout/index.php`,
  `payment/stub.php`, `account/details.php`), 6 мест вызова. Комментарий
  `// TODO: заменить на реальный номер... перед продакшеном` над ними
  уходит вместе с константами.
- `admin-header.php` — `$adminNavItems` сейчас плоский массив без учёта
  роли; это первое место, где появляется фильтрация по `roles`.
- `requireRole()`/`homeUrlForRole()` (`functions.php:231-251`) —
  `manager` на `requireRole(['admin'])` получит `redirect('/admin')`,
  не 403/404.
- Готового паттерна «кэш на один запрос» в проекте нет (`getActiveBanners()`
  в `Banner.php` — без кэша) — `setting()` первый такой случай, кэш —
  статическая переменная внутри функции.
- Последний ADR в `planning-log.md` — `ADR-044`; новый — `ADR-045`.
- `normalizePhone()`/`validatePhone()` уже есть в `Core/Validation.php`
  — переиспользуются для валидации телефона в форме настроек, новый
  валидатор не пишется.

## Scope — что трогаем
- [x] `database/install.php` — изменить: таблица `settings`
      (`key VARCHAR(60) NOT NULL UNIQUE`, `value TEXT NOT NULL`,
      `updated_at`), сид текущих значений констант через `INSERT IGNORE`
- [x] `.docs/database.md` — изменить: раздел `settings`
- [x] `.docs/planning-log.md` — изменить: `ADR-045` (реквизиты магазина
      в БД, секреты остаются в `.env`)
- [x] `src/Core/Settings.php` — создать: `SETTING_KEYS` (whitelist
      ключей с подписями), `setting(string $key): string` (читает
      `getAllSettings()` один раз на запрос, кэш в статической
      переменной), `phoneToTel(string): string` (`+7 900 000-00-00` →
      `+79000000000`), `validateSettingsInput(array): array` — чистые
      функции без БД
- [x] `tests/Unit/SettingsTest.php` — создать: `phoneToTel()` на
      валидном/невалидном номере, `validateSettingsInput()` — пустое
      обязательное поле, невалидный телефон, не-`https` URL карты,
      неизвестный ключ игнорируется
- [x] `tests/bootstrap.php` — изменить: `require_once` `Core/Settings.php`
- [x] `src/Models/Setting.php` — создать: `getAllSettings(): array`,
      `updateSettings(array $values): void` (только ключи из
      `SETTING_KEYS`, транзакция)
- [x] `src/Controllers/AdminSettingController.php` — создать: `index()`,
      `update()` — оба `requireRole(['admin'])`, POST — `requireCsrf()`
      → при ошибке прямой рендер с `$old`/`$errors`, при успехе
      `redirect()`
- [x] `src/Views/admin/settings/index.php` — создать: форма реквизитов
      магазина, блок «О системе» (`APP_ENV`, `PHP_VERSION`, версия
      MySQL, размер `storage/logs/app.log`, доступность записи в
      `public/uploads/`)
- [x] `src/Views/layout/admin-header.php` — изменить: у элементов
      `$adminNavItems` появляется необязательный `roles`, массив
      фильтруется по `currentUser()['role']`; пункт «Настройки» с
      `roles => ['admin']`
- [x] `config/routes.php` — изменить: `GET/POST /admin/settings`
- [x] `config/config.php` — изменить: удалить `SHOP_PHONE`,
      `SHOP_PHONE_TEL`, `SHOP_WHATSAPP_URL` и TODO-комментарий над ними
- [x] `src/Views/layout/footer.php`, `src/Views/checkout/index.php`,
      `src/Views/payment/stub.php`, `src/Views/account/details.php` —
      изменить: `setting('shop_phone')`, `phoneToTel(setting('shop_phone'))`,
      `setting('shop_whatsapp_url')` вместо констант
- [x] `.docs/dev-log.md` — запись по итогам таска

**Дополнительно, не в исходном плане:** `src/Views/layout/header.php`
— добавлен `require_once Models/Setting.php` (по образцу того, как
`admin-header.php` уже требует `Models/Review.php` для бейджа
отзывов) — иначе `setting()` был бы недоступен в `footer.php` и других
View витрины, подключающих `header.php` раньше себя.

## Out of scope — не трогаем
- Таски 3-8: свои View `/about`/`/contacts`/`/showroom`, форма
  обратного звонка, редактирование текстов/фото и баннеров в Панели,
  сотрудники
- Секреты и ключи интеграций — не переезжают из `.env` (`Q-032`)
- Принудительное завершение сессий других пользователей
- Любой рефакторинг существующих Model/Controller сверх перечисленного
  выше

## Definition of Done
- [x] `manager` на `/admin/settings` → редирект на `/admin`, пункта
      «Настройки» в сайдбаре нет; `admin` — форма и блок «О системе»;
      `customer` → редирект на `/account`; Гость → `/login`; POST без
      CSRF → 419
- [x] Смена телефона в форме → новое значение в футере, чекауте,
      заглушке оплаты и подсказке кабинета без деплоя; `tel:` собран из
      нового номера через `phoneToTel()`
- [x] Неизвестный ключ в POST игнорируется (не пишется в БД);
      невалидный телефон / не-`https` URL карты / пустое обязательное
      поле — поле подсвечено, БД не изменена
- [x] На любую страницу витрины — ровно один `SELECT ... FROM settings`
      (кэш на запрос); `grep -rn SHOP_ src/ config/` — пусто
- [x] `composer test` зелёный (новые тесты `phoneToTel()`,
      `validateSettingsInput()`)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- Каждый шаг проверяется тем, что указано в DoD: чистая логика —
  `composer test`, маршруты/доступ по роли/CSRF — живым HTTP на
  реальной БД, вёрстка — вручную в браузере
