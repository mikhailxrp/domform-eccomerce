# Current Task

## Фаза
Phase 8 — Админ-панель (контент и доступ) и статические страницы
(`.docs/phases/phase-8.md`), Таск 8 из 8 (последний таск фазы).

**Статус:** ✅ Завершён 22.09.2026 — проверено на реальной БД живым
HTTP (`php -S` + `curl`), тремя временными пользователями ролей
`admin`/`manager`/`customer` (удалены после проверки, включая их
`remember_tokens`): `admin` → 200 на `/admin/users` со списком и
формой; `manager` → редирект на `/admin`, в сайдбаре нет пунктов
«Сотрудники»/«Настройки»; `customer` → редирект `/account`; Гость →
`/login`; POST без CSRF → 419. Создание Менеджера через форму →
строка появилась в списке; дубликат email / пароль короче 8 / роль вне
`STAFF_ROLES` — поля подсвечены, в БД лишних строк нет (проверено
отдельным запросом). Блокировка собственной учётки → flash-отказ,
`is_blocked` не изменился. Блокировка другого Менеджера →
`is_blocked=1`, его `remember_tokens` удалены: новый вход с тем же
паролем → общий `AUTH_ERROR` (причина не раскрывается); чистая сессия
с одним только старым `remember_token`-cookie (после удаления токена
из БД) → редирект на `/login`, не авто-вход; уже открытая до блокировки
сессия не обрывается принудительно — осознанно вне скоупа (правило 3
`FR-ADM-007` про «следующую попытку входа»). «Заблокировать»
несуществующего id и id Покупателя (`role='customer'`, не
`manager`/`admin`) → одинаковый flash «не найден» — `setUserBlocked()`
ограничена `role IN ('manager','admin')` прямо в SQL, Покупателя через
этот маршрут заблокировать нельзя, даже подставив его id. Разблокировка
возвращает доступ (подтверждено повторным логином). `storage/logs/
app.log` — без новых ошибок (только ожидаемый `WARNING` о неудачной
попытке входа заблокированного тестового Менеджера). `composer test`
— 408/408 (+13 `StaffFormTest`). Реализовано по плану ниже без
отклонений.

## Задача
`FR-ADM-007` п. 1–3 — `/admin/users` только для `admin`: список
пользователей с ролями `manager`/`admin` (имя, email, телефон, роль,
дата, бейдж «Заблокирован»); форма создания (имя, email, пароль ≥ 8,
роль, телефон необязателен); «Заблокировать» / «Разблокировать». Себя
заблокировать нельзя. Менеджер раздел не видит и не открывает.

## Что проверено в коде перед планом
- `src/Models/User.php` — уже есть `findUserByEmail()`,
  `findUserById()`, `createUser()` (паттерн перехвата дубликата email
  через `errorInfo[1] === 1062` → `null`), `updateUserPasswordHash()` —
  новые функции добавлены рядом по тому же стилю.
- `src/Core/functions.php:231` — `requireRole(array $roles)` готов.
- `src/Core/Validation.php` — `normalizePhone()`, `validatePassword()`
  готовы, переиспользованы как есть.
- `src/Models/RememberToken.php:50` — `deleteRememberTokens(int
  $userId)` готов.
- `src/Views/layout/admin-header.php` — `$adminNavItems` уже
  поддерживает необязательный ключ `roles` (фильтрация по
  `currentUser()['role']`), пункт «Настройки» — готовый образец
  `roles => ['admin']`.
- `config/routes.php` — секции GET/POST уже содержат
  `/admin/content*`, `/admin/settings` как образец для новых
  `/admin/users*` маршрутов.
- `src/Controllers/AdminSettingController.php` — образец
  admin-only контроллера (`requireRole(['admin'])` во всех методах).

## Scope — что трогали
- [x] `src/Core/StaffForm.php` — создан: `STAFF_ROLES`,
      `validateStaffInput(array): array`
- [x] `tests/Unit/StaffFormTest.php` — создан (13 тестов)
- [x] `tests/bootstrap.php` — подключён `Core/StaffForm.php`
- [x] `src/Models/User.php` — добавлены `getStaffUsers()`,
      `createStaffUser()`, `setUserBlocked()` (ограничена
      `role IN ('manager','admin')` в SQL — сверх исходного плана,
      защита от блокировки Покупателя по id)
- [x] `src/Controllers/AdminUserController.php` — создан: `index()`,
      `store()`, `block()`, `unblock()`
- [x] `src/Views/admin/users/index.php` — создан: таблица сотрудников
      + форма создания на одной странице
- [x] `src/Views/layout/admin-header.php` — пункт «Сотрудники» с
      `roles => ['admin']`
- [x] `config/routes.php` — маршруты `/admin/users*`
- [x] `.docs/dev-log.md`, `.docs/phases/phase-8.md` — запись по итогам
      таска

## Out of scope — не трогали
- Смена роли/пароля другого сотрудника Администратором
- Принудительное завершение активной сессии при блокировке (только
  `deleteRememberTokens()`)
- Редактирование профиля Покупателей — не входит в список
  `/admin/users`
- `editprofile.html` из макетов — не использован, только
  `userlist.html` (список + создание)
- Закрытие фазы (`_status.md`, `tz-coverage.md`, `planning-log.md`,
  `admin-assembly.md`) — не тронуты, это Таск 8 и был последним в
  Фазе 8, но её формальное закрытие — отдельный шаг

## Definition of Done
- [x] Созданный Менеджер входит по выданным email/паролю и видит
      Панель без пунктов «Сотрудники»/«Настройки»; `/admin/users` и
      `/admin/settings` под ним → редирект на `/admin`
- [x] Заблокированный Менеджер: вход → общее сообщение ошибки; remember-
      cookie не восстанавливает сессию, его строки `remember_tokens`
      удалены при блокировке; «Разблокировать» возвращает доступ
- [x] Блокировка собственной учётки → flash-отказ, `is_blocked` не
      изменился
- [x] Дубликат email / пароль < 8 / роль вне `STAFF_ROLES` —
      подсветка полей, строки в БД нет; телефон пустой — допускается
- [x] `manager`/`customer` на `/admin/users` → редирект; Гость →
      `/login`; POST без CSRF → 419
- [x] Покупатели (`role='customer'`) в списке `/admin/users` не
      показываются
- [x] `composer test` зелёный (408/408, новые тесты
      `validateStaffInput()`)
- [x] Проверить `.docs/dod-global.md`
- [x] Все тестовые учётные записи, созданные при ручной проверке,
      удалены после проверки

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
- Каждый шаг проверялся тем, что указано в DoD: доступ/блокировка/
  CSRF — живым HTTP на реальной БД, чистая логика — `composer test`
