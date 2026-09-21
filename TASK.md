# Current Task

## Фаза
Phase 7 — Кабинет покупателя и уведомления
(`.docs/phases/phase-7.md`), Таск 3 из 9.

**Статус:** ✅ Завершён 21.09.2026. Проверено на реальной БД живым HTTP
(`php -S` + `curl`): смена имени без пароля, смена email без пароля
(отклонено), смена email с неверным паролем и на чужой email (оба
отклонены общим сообщением, оба поля подсвечены), смена email с верным
паролем (успех, вход по новому email работает, по старому — нет),
смена пароля (пустая форма/короче 8/несовпадение/совпадение с текущим
— отклонено; неверный текущий — отклонено; успех — старый пароль
не входит, старый remember-cookie не авторизует, id сессии сменился),
419 без CSRF на обеих формах, подделанный `phone` в POST проигнорирован.
Тестовые Покупатели удалены после проверки. `composer test` — 308/308
(было 296, +12 `AccountTest`). Одно отклонение от плана, найдено в
процессе живой проверки: текст подсказки под полем `email`/
`current_password` изначально оставался специфичным («Введите
корректный email» / «Укажите текущий пароль») даже когда причина
отказа — неверный пароль или занятый email, а не формат/пустота; это
частично раскрывало, какая из двух business-rule причин сработала
(нарушение DoD). Исправлено отдельным флагом `account_error`/
`wrong_password` в `$errors`/`$passwordErrors` — текст под полем
подменяется на общую формулировку только для business-rule случая,
специфичные сообщения (пустое поле, неверный формат) остаются как есть.

## Задача
`FR-ACC-004` — `/account/details`: форма «имя + email» и отдельный блок
смены пароля (текущий/новый/подтверждение). Смена email и смена пароля
требуют текущий пароль; имя меняется без него. Телефон — read-only с
контактом Менеджера. После смены пароля — инвалидация remember-токенов
и текущей сессии.

## Scope — что трогаем

- [ ] `src/Core/Account.php` — создать: `validateProfileInput(array
      $input, string $currentEmail): array` (имя 2–100, `validateEmail()`,
      `current_password` обязателен, если email изменился),
      `validatePasswordChangeInput(array $input): array`
      (`validatePassword()` ≥8, совпадение подтверждения, новый ≠
      текущему) — чистые функции без БД, по образцу `Core/Checkout.php`
- [ ] `tests/Unit/AccountTest.php` — создать
- [ ] `tests/bootstrap.php` — изменить: подключить `Core/Account.php`
- [ ] `src/Models/User.php` — изменить: `updateUserProfile(int $userId,
      string $name, string $email): void`, `isEmailTakenByOther(string
      $email, int $userId): bool`
- [ ] `src/Controllers/AccountController.php` — изменить: `details()`,
      `updateDetails()`, `updatePassword()` — `requireCsrf()`,
      `password_verify()` текущего пароля в Controller (Model паролей
      не проверяет), общее сообщение об ошибке без различения причины;
      успех смены пароля — `updateUserPasswordHash()` +
      `deleteRememberTokens()` + `regenerateSession()`
- [ ] `src/Views/account/details.php` — создать: по «Account Details»
      `my-account.html` (одно поле имени, без First/Last/Display Name
      — в `users` только `name`), телефон read-only + `SHOP_PHONE`,
      блок смены пароля через уже существующий
      `components/password-field.php`
- [ ] `config/routes.php` — изменить: `GET /account/details`,
      `POST /account/details`, `POST /account/password`

## Out of scope — не трогаем

- История заказов, адреса, избранное, СМС — Таски 2, 4–9 этой же фазы
- Редактирование телефона — по `FR-ACC-004` правило 3 доступно только
  через Менеджера, не строится
- `AuthController` (login/register/forgot) — не трогается, только
  переиспользуются его helpers (`deleteRememberTokens`,
  `regenerateSession`)
- `account-sidebar.php` — ссылка на «Личные данные» уже проставлена в
  Таске 1

## Definition of Done

- [x] Смена email без текущего пароля → отклонено, поле подсвечено; с
      верным паролем — email изменён в БД, вход по новому email работает
- [x] Неверный текущий пароль / email занят другим пользователем →
      одно общее сообщение, без раскрытия причины (`php.md`, без
      перечисления аккаунтов) — включая текст под полем (см. отклонение
      выше)
- [x] Имя меняется без ввода пароля
- [x] Поля `phone` в форме нет; подделанный `phone` в POST → в БД не
      изменился; на странице показан `SHOP_PHONE`
- [x] После смены пароля: вход по старому паролю невозможен; cookie
      `remember_token` с другого устройства (созданная заранее) больше
      не авторизует; id текущей сессии сменился (`regenerateSession()`)
- [x] Пустая форма / пароль короче 8 / несовпадающее подтверждение →
      поля подсвечены, введённые значения (кроме паролей) сохранены;
      419 без CSRF на обеих формах
- [x] `composer test` зелёный, включая `AccountTest` (308/308)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
