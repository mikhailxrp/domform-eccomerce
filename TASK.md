# Current Task

## Фаза
Phase 0 — Фундамент (`.docs/phases/phase-0.md`, Таск 5)

## Задача
`FR-AUTH-003`: Покупатель с экрана входа указывает email, получает
одноразовую ссылку (в `app.log` локально / письмом на проде) и
устанавливает новый пароль — без раскрытия, зарегистрирован ли email.

## Scope — что трогаем

- [ ] `src/Models/PasswordReset.php` — создать:
      `createPasswordReset(int $userId): string` (возвращает сырой
      токен; в БД — только `sha256`-хэш; перед созданием новой записи
      аннулирует прежние неиспользованные токены этого пользователя —
      причина `INDEX(user_id)` в `database.md`),
      `findValidPasswordReset(string $token): ?array` (не истёк, не
      использован), `markPasswordResetUsed(int $id): void`
- [ ] `src/Models/User.php` — изменить: добавить
      `updateUserPasswordHash(int $userId, string $passwordHash): void`
- [ ] `src/Services/Mailer.php` — создать:
      `sendMail(string $to, string $subject, string $body): void`
      (`match` по `env('MAIL_DRIVER')`: `log` → `logInfo()` с телом
      письма, `mail` → PHP `mail()`),
      `renderEmailBody(string $template, array $data): string`
      (рендерит `src/Views/emails/*.php` в строку — без
      `header.php`/`footer.php`, это не HTML-страница сайта)
- [ ] `src/Views/emails/password-reset.php` — создать: текст письма со
      ссылкой на `/reset-password/{token}`
- [ ] `src/Controllers/AuthController.php` — изменить: `showForgot`,
      `forgot` (генерик-ответ, `tooManyAttempts('forgot', …)`),
      `showReset`, `reset` (валидация тем же `validatePassword()`, что
      регистрация; после смены — `deleteRememberTokens()` из Таска 4,
      редирект на `/login` с флэшем — без авто-логина, по прецеденту
      `register()`)
- [ ] `src/Views/auth/forgot.php` — создать: форма email
- [ ] `src/Views/auth/reset.php` — создать: форма нового пароля (один
      пароль, без «подтверждения» — по прецеденту `register.php`)
- [ ] `src/Views/auth/login.php` — изменить: добавить рабочую ссылку
      «Забыли пароль?» → `/forgot-password` (сознательно отложена в
      Таске 3)
- [ ] `config/routes.php` — изменить: `GET`+`POST /forgot-password`,
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

- [ ] Для существующего и несуществующего email — идентичный ответ
      «если адрес зарегистрирован, письмо отправлено»; в `app.log`
      (`MAIL_DRIVER=log`) ссылка появляется только для реально
      существующего email
- [ ] Повторный запрос сброса для того же пользователя аннулирует
      прежний неиспользованный токен (проверка в БД — старая запись
      помечена использованной/удалена)
- [ ] В БД хранится только хэш токена; токен одноразовый (`used_at`),
      живёт 60 минут; повторное открытие использованной или протухшей
      ссылки — «ссылка недействительна», без ошибки в `app.log`
- [ ] После смены пароля: `password_verify()` новым паролем проходит,
      старым — нет; удалены все `remember_tokens` пользователя (БД);
      пароль короче 8 символов отклоняется той же `validatePassword()`
- [ ] `/forgot-password` ограничен по частоте (`tooManyAttempts('forgot', …)`,
      проверка серией быстрых запросов)
- [ ] Обе формы — `csrfField()` в разметке и `requireCsrf()` в
      контроллере до обращения к Model
- [ ] Ссылка «Забыли пароль?» на `/login` ведёт на рабочую форму
- [ ] `composer test` зелёный (без новых тестов — см. Out of scope)
- [ ] Проверить `.docs/dod-global.md` (применимо: «Безопасность»,
      «Формы и валидация», «Код»)

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
