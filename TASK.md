# Current Task

## Фаза
Phase 0 — Фундамент (`.docs/phases/phase-0.md`, Таск 1)

## Задача
Привести `database/install.php` в соответствие с `.docs/database.md`
(4 пропущенных ревью изменения + 2 новые таблицы для `FR-AUTH-003/004`)
и добавить `database/seed.php`, создающий первого Администратора из `.env`.

## Scope — что трогаем

- [x] `database/install.php` — изменить:
  - `users`: `+ is_blocked TINYINT(1) NOT NULL DEFAULT 0` (`ADR-025`)
  - `products`: `+ is_featured TINYINT(1) NOT NULL DEFAULT 0`
    + `KEY idx_products_featured (is_featured)` (`ADR-024`)
  - новые таблицы сразу после `users`: `remember_tokens`, `password_resets`
  - новые таблицы в конце: `content_pages` (`ADR-022`), `banners` (`ADR-023`)
    — структура строго по `database.md`
  - docblock: запуск через CLI `php database/install.php`, не
    «`/install-temp.php` через браузер»
- [x] `.docs/database.md` — изменить: разделы `password_resets` и
  `remember_tokens` в том же формате, что остальные (колонка | тип |
  назначение, индексы, WHY), карта связей (`users` ──< обе,
  `ON DELETE CASCADE`)
- [x] `.docs/planning-log.md` — изменить: `ADR-027` (`password_resets`),
  `ADR-028` (`remember_tokens`, selector/validator вместо одного токена)
- [x] `database/seed.php` — создать: читает `ADMIN_NAME` / `ADMIN_EMAIL` /
  `ADMIN_PASSWORD` через `env()`, валидирует (email, пароль ≥ 8),
  `password_hash()`, вставляет `role='admin'`, `phone NULL`; если email
  уже есть — сообщение и выход без изменений
- [x] `.env.example` — изменить: блок `ADMIN_*` (пустые значения),
  `MAIL_DRIVER=log`

### Структура новых таблиц

`password_resets`

| Колонка | Тип | Назначение |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| user_id | INT NOT NULL, FK → users.id, ON DELETE CASCADE | |
| token_hash | CHAR(64) NOT NULL UNIQUE | sha256 от случайного токена (32 байта) — по нему ищем; сам токен только в ссылке письма |
| expires_at | DATETIME NOT NULL | created + 60 мин |
| used_at | DATETIME NULL | одноразовость: заполнен = ссылка недействительна |
| created_at | TIMESTAMP DEFAULT NOW | |

Индексы: `UNIQUE(token_hash)`, `INDEX(user_id)`

`remember_tokens`

| Колонка | Тип | Назначение |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| user_id | INT NOT NULL, FK → users.id, ON DELETE CASCADE | |
| selector | CHAR(24) NOT NULL UNIQUE | публичная часть cookie — по ней ищем строку |
| token_hash | CHAR(64) NOT NULL | sha256 секретной части cookie; сравнение через `hash_equals()` |
| expires_at | DATETIME NOT NULL | created + 30 дней |
| created_at | TIMESTAMP DEFAULT NOW | |

Индексы: `UNIQUE(selector)`, `INDEX(user_id)`

Допущение: `token_hash` — `sha256`, не `password_hash()`: токены —
32 случайных байта, bcrypt для них избыточен и не позволил бы искать по
`UNIQUE`-индексу.

## Out of scope — не трогаем

- `src/Models/*`, `src/Controllers/*`, `src/Views/*`,
  `src/Services/Mailer.php` — Таски 2–5 этой фазы
- `src/Core/*`, `config/*`, `public/*` — не трогаем
- Механизм миграций / `ALTER TABLE` для уже развёрнутой старой схемы —
  не заводим: `install.php` рассчитан на чистую БД, старую (если
  создавали) дропнуть вручную
- Тестовые данные каталога (категории/товары/варианты) — фикстуры Фазы 1
- Реальный `.env` — заполняется вручную, в репозиторий не попадает
- Остальные таблицы в `install.php` — не менять, даже если что-то
  захочется «поправить попутно»

## Definition of Done

- [x] На чистой БД `php database/install.php` завершается «✅ Таблицы
      созданы успешно»; второй запуск подряд — тоже без ошибок
- [x] `SHOW TABLES` — 18 таблиц: 14 существующих + `remember_tokens`,
      `password_resets`, `content_pages`, `banners` (в момент написания
      таска ошибочно посчитано 12 существующих — их 14, итог 18, не 16;
      проверено фактическим запуском против БД)
- [x] `SHOW CREATE TABLE users` содержит `is_blocked`; `products` —
      `is_featured` и индекс `idx_products_featured`
- [x] `SHOW CREATE TABLE` для 4 новых таблиц совпадает с `database.md` по
      колонкам, типам, FK и индексам
- [x] `php database/seed.php` с заполненными `ADMIN_*` → в `users` одна
      строка `role='admin'`, `password_hash` начинается с `$2y$`,
      `phone` NULL, `is_blocked` 0
- [x] Повторный `seed.php` → сообщение «уже существует», строк
      по-прежнему одна
- [x] `seed.php` с `ADMIN_PASSWORD` короче 8 символов или невалидным
      email — отказ с понятным сообщением, строка не создана
- [x] `seed.php` без `ADMIN_*` в `.env` — понятная ошибка `env()`, не
      PHP-warning
- [x] `grep -r "ADMIN_PASSWORD"` по репозиторию находит только
      `.env.example` и `seed.php` (чтение переменной) — пароля в
      репозитории нет
- [x] `database.md` и `install.php` описывают одинаковые 18 таблиц
      (ручная сверка по списку)
- [x] `.docs/planning-log.md` — добавлены `ADR-027`, `ADR-028`
- [x] Проверить `.docs/dod-global.md` (применимы: «Данные» → запись в
      БД; «Код» → нет PHP-ошибок и warnings) — `composer test` зелёный
      (18/18), `storage/logs/app.log` без новых записей после прогона

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
