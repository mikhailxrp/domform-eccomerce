# Current Task

## Фаза
Phase 8 — Админ-панель (контент и доступ) и статические страницы
(`.docs/phases/phase-8.md`), Таск 4 из 8.

**Статус:** ✅ Завершён 22.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`): `/contacts` → 200 (реквизиты из `settings`,
текст из `content_pages.contacts`, форма рендерится); `/pages/contacts`
→ 301 на `/contacts` (то же решение, что в Таске 3), остальные 5
`/pages/{slug}` и `/about`/`/showroom` — без регрессии; POST без CSRF
→ 419; невалидный телефон — подсветка поля, строки в БД нет; валидная
отправка — строка `status='new'`, flash «Спасибо! Мы перезвоним в
ближайшее время»; 4-я отправка за 10 минут заблокирована
(`tooManyAttempts('callback', 3, 600)`) — ровно 3 строки в БД, не 4;
авторизованный тестовый Покупатель (создан/удалён вручную на время
проверки) видит имя и телефон уже подставленными. Кириллица через
`curl --data-urlencode` на этой машине бьётся аргументами консоли (тот
же артефакт, что в Таске 2) — обойдено телом запроса из файла,
приложение не при чём (подробности — `dev-log.md` 22.09.2026). Все
тестовые заявки и временный пользователь удалены после проверки,
`storage/logs/app.log` — без новых записей. `composer test` —
375/375 (+11 `CallbackTest`). Реализовано по плану ниже без отклонений.

## Задача
`FR-CNT-001` — `/contacts`: адрес цеха, телефон, email, кнопка
WhatsApp (из `settings`), редактируемый текст из
`content_pages.contacts`, форма «Перезвоните мне» (имя, телефон,
комментарий). Отправка → строка в новой таблице `callback_requests`
со статусом `new` + flash «Мы перезвоним». Rate-limit по образцу
формы отзыва.

## Что проверено в коде перед планом
- `PageController::show()` уже перехватывает `about`/`showroom` и
  делает 301 на новый маршрут раньше проверки whitelist (Таск 3) —
  `contacts` добавлена в тот же список, `show()` для остальных 5
  slug не менялся.
- Ссылка «Контакты» на `/pages/contacts` встречалась в трёх местах:
  `header.php` — десктопное и мобильное меню (две копии, как и было
  с «О компании» в Таске 1), `footer.php` — колонка «Информация».
- Образец формы с прямым рендером той же страницы при ошибке (без
  редиректа, `$old`/`$errors`) — `CheckoutController::renderCheckoutPage()`;
  для `contacts()` заведён аналогичный приватный параметр `$old`/`$errors`
  по умолчанию `[]`, вызывается из `storeCallback()` напрямую при ошибке.
- Образец rate-limit — `ReviewController::store()`:
  `tooManyAttempts('review', 3, 600)` / `hitRateLimit('review')`, лимит
  не снимается успешной отправкой — для `callback` тот же принцип и
  те же значения (3 / 600 сек).
- `normalizePhone()`/`validatePhone()` уже были в `Core/Validation.php`
  — переиспользованы в `validateCallbackInput()`, свой regex не писался.
- Подстановка имени/телефона авторизованного Покупателя — как на
  `/checkout` (`findUserById($user['id'])`, `currentUser()` не хранит
  телефон в сессии).

## Scope — что трогали
- [x] `database/install.php` — таблица `callback_requests`
- [x] `.docs/database.md` — раздел `callback_requests`
- [x] `.docs/planning-log.md` — `ADR-047`
- [x] `src/Core/Callback.php` — создан: `CALLBACK_STATUS_NEW`,
      `CALLBACK_STATUS_PROCESSED`, `validateCallbackInput()`,
      `normalizeCallbackInput()`
- [x] `tests/Unit/CallbackTest.php` — создан (11 тестов)
- [x] `tests/bootstrap.php` — подключён `Core/Callback.php`
- [x] `src/Models/CallbackRequest.php` — создан: `createCallbackRequest()`
- [x] `src/Controllers/PageController.php` — `show()` — редирект для
      `contacts`; `contacts(array $old = [], array $errors = [])`;
      `storeCallback()`
- [x] `src/Views/pages/contacts.php` — создан
- [x] `config/routes.php` — `GET /contacts`, `POST /contacts/callback`
- [x] `src/Views/layout/header.php` — обе копии меню
- [x] `src/Views/layout/footer.php` — ссылка «Контакты»
- [x] `.docs/dev-log.md` — запись по итогам таска

## Out of scope — не трогали
- `/admin/callbacks` — список заявок и бейдж в Панели — Таск 5
- Редактирование текста/фото страниц в Панели — Таск 6
- `settings`/`content_pages` — только читались, не менялись
- Любой рефакторинг `pages/show.php` сверх добавления `contacts` в
  список редиректов

## Definition of Done
- [x] Валидная форма → строка в `callback_requests` (`status='new'`),
      flash об успехе, форма очищена; пустая/невалидная (телефон
      «123») — поля подсвечены, строки в БД нет
- [x] 4-я отправка за 10 минут с одного клиента → отказ с сообщением,
      строки нет; без CSRF → 419
- [x] Реквизиты на странице совпадают с `settings`; кнопка WhatsApp
      ведёт на `shop_whatsapp_url` с `rel="noopener"`
- [x] `/pages/contacts` → 301 на `/contacts` (то же решение, что в
      Таске 3); остальные 5 `/pages/{slug}` работают как раньше
      (регрессия)
- [x] Авторизованный Покупатель видит имя/телефон уже подставленными
      в форме
- [x] Страница проверена на 320px по коду (переиспользованы уже
      проверенные в Тасках 1–3 адаптивные классы `single-form`,
      `showroom-info-card`, `row g-4`/`col-lg-*`; отдельный визуальный
      просмотр в браузере на этой машине недоступен — см. примечание
      ниже)
- [x] `composer test` зелёный (375/375, новые тесты `validateCallbackInput()`)
- [x] Проверить `.docs/dod-global.md`

**Примечание по проверке:** в этой сессии нет браузера — DoD по
вёрстке/320px проверен чтением кода и переиспользованием уже
провизуально проверенных в предыдущих тасках классов, а не
скриншотом. Функциональность (маршруты, форма, rate-limit, CSRF,
запись в БД, регрессия) проверена живым HTTP на реальной БД.

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- Каждый шаг проверялся тем, что указано в DoD: форма/редиректы/
  rate-limit — живым HTTP на реальной БД, регрессия — `composer test`
