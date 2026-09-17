# Current Task

## Фаза
Phase 0 — Фундамент (`.docs/phases/phase-0.md`, Таск 5)

## Задача
`FR-AUTH-003`: Покупатель с экрана входа указывает email, получает
одноразовую ссылку (в `app.log` локально / письмом на проде) и
устанавливает новый пароль — без раскрытия, зарегистрирован ли email.

## Scope — что трогаем

- [x] `src/Models/PasswordReset.php` — создать:
      `createPasswordReset(int $userId): string` (возвращает сырой
      токен; в БД — только `sha256`-хэш; перед созданием новой записи
      аннулирует прежние неиспользованные токены этого пользователя —
      причина `INDEX(user_id)` в `database.md`),
      `findValidPasswordReset(string $token): ?array` (не истёк, не
      использован), `markPasswordResetUsed(int $id): void`
- [x] `src/Models/User.php` — изменить: добавить
      `updateUserPasswordHash(int $userId, string $passwordHash): void`
- [x] `src/Services/Mailer.php` — создать:
      `sendMail(string $to, string $subject, string $body): void`
      (`match` по `env('MAIL_DRIVER')`: `log` → `logInfo()` с телом
      письма, `mail` → PHP `mail()`),
      `renderEmailBody(string $template, array $data): string`
      (рендерит `src/Views/emails/*.php` в строку — без
      `header.php`/`footer.php`, это не HTML-страница сайта)
- [x] `src/Views/emails/password-reset.php` — создать: текст письма со
      ссылкой на `/reset-password/{token}`
- [x] `src/Controllers/AuthController.php` — изменить: `showForgot`,
      `forgot` (генерик-ответ, `tooManyAttempts('forgot', …)`),
      `showReset`, `reset` (валидация тем же `validatePassword()`, что
      регистрация; после смены — `deleteRememberTokens()` из Таска 4,
      редирект на `/login` с флэшем — без авто-логина, по прецеденту
      `register()`)
- [x] `src/Views/auth/forgot.php` — создать: форма email
- [x] `src/Views/auth/reset.php` — создать: форма нового пароля (один
      пароль, без «подтверждения» — по прецеденту `register.php`)
- [x] `src/Views/auth/login.php` — изменить: добавить рабочую ссылку
      «Забыли пароль?» → `/forgot-password` (сознательно отложена в
      Таске 3)
- [x] `config/routes.php` — изменить: `GET`+`POST /forgot-password`,
      `GET`+`POST /reset-password/{token}`

## Out of scope — не трогаем

- Поле «Подтверждение пароля» — по прецеденту `register.php`
- Смена пароля из личного кабинета (по факту логина) — другая фаза
  (личный кабинет — Фаза 7)
- Реальный SMTP/сторонний провайдер почты — только `PHP mail()`/лог,
  так решено в `phase-0.md`
- `database/install.php` / `.docs/database.md` — таблица
  `password_resets` уже развёрнута Таском 1
- Новые unit-тесты — все функции `PasswordReset.php`/`Mailer.php`
  трогают БД/IO, по правилу `php.md` не юнит-тестируются (тот же
  прецедент, что `User.php`/`RememberToken.php` без тестов)
- Каталог/корзина/чекаут — другие фазы

## Definition of Done

- [x] Для существующего и несуществующего email — идентичный ответ
      «если адрес зарегистрирован, письмо отправлено» (проверено
      `curl` — оба случая дают буквально одинаковый флэш-текст); в
      `app.log` (`MAIL_DRIVER=log`) ссылка появляется только для
      реально существующего email — проверено напрямую вызовом
      `createPasswordReset()`/`renderEmailBody()` (боевой
      `APP_LOG_LEVEL=error` в `.env` фильтрует уровень `info`, как и в
      Таске 3 с `logWarning()` — это настройка окружения, не баг; сам
      `sendMail('log', …)` вызывает `logInfo()` корректно)
- [x] Повторный запрос сброса для того же пользователя аннулирует
      прежний неиспользованный токен — проверено напрямую: первый
      токен валиден сразу после создания, становится невалидным после
      второго запроса, второй остаётся валидным
- [x] В БД хранится только хэш токена; токен одноразовый (`used_at`),
      живёт 60 минут; повторное открытие использованной ссылки —
      редирект на `/forgot-password` с «ссылка недействительна», без
      ошибки в `app.log`; несуществующий токен — тот же результат
- [x] После смены пароля: вход новым паролем — 302 успех, старым —
      сгенерик-ошибка (проверено `curl` против реальной БД); удаление
      `remember_tokens` переиспользует функцию Таска 4 (код-ревью, тот
      же вызов, что в `logout()`); пароль короче 8 символов отклоняется
      `validatePassword()` — поле подсвечено `is-invalid`
- [x] `/forgot-password` ограничен по частоте — 4-й быстрый запрос за
      минуту (`tooManyAttempts('forgot', 3, 60)`) отклонён
- [x] Обе формы — `csrfField()` в разметке и `requireCsrf()` в
      контроллере; запрос без `_csrf` на оба POST-эндпоинта — 419
- [x] Ссылка «Забыли пароль?» на `/login` ведёт на рабочую форму —
      проверено наличием `href="/forgot-password"` в разметке `/login`
- [x] `composer test` — 40/40 зелёных (без новых тестов — см. Out of
      scope)
- [x] Проверить `.docs/dod-global.md` (применимо: «Безопасность»,
      «Формы и валидация», «Код»)

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
