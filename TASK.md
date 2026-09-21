# Current Task

## Фаза
Phase 7 — Кабинет покупателя и уведомления
(`.docs/phases/phase-7.md`), Таск 4 из 9.

**Статус:** ✅ Завершён 21.09.2026. Проверено на реальной БД живым HTTP
(`php -S` + `curl`): пустая форма (город/улица/дом подсвечены), 419 без
CSRF, добавление первого адреса (стал основным автоматически), второго
(не стал), «Сделать основным» (переключение), редактирование
(предзаполнение формы + сохранение), удаление обычного адреса,
удаление основного (переназначен самый ранний оставшийся), изоляция по
`user_id` (чужой `id` на update → 404, на delete/default → «не
найден», данные не тронуты), лимит `ACCOUNT_ADDRESSES_MAX` (11-й адрес
отклонён, count остался 10, flash + inline-сообщение). `php
database/install.php` дважды подряд — без ошибок, структура таблицы
сверена. Тестовые Покупатели и адреса удалены после проверки.
`composer test` — 323/323 (было 308, +15 `AddressTest`).

Одно отклонение от плана, найдено в процессе живой проверки:
`validateAddressInput()` изначально не обрезала пробелы сама (ожидала
уже нормализованный ввод от вызывающего кода) — тест с `' '` (пробел)
как «пустой город» падал. Приведено к стилю `validateProfileInput()`
(Таск 3, `Core/Account.php`) — обрезает пробелы внутри себя, не
полагаясь на вызывающий код.

Артефакт тестирования (не код): несколько тестов с кириллицей через
`curl -d`/`--data-urlencode` в этом Windows/Git-Bash окружении дали
пустые поля в БД при прямой передаче — тот же баг инструмента, что уже
фиксировался в `dev-log.md` для формы отзывов Фазы 6; обойдено
предварительным `rawurlencode()` значений. Прямой вызов
`createAddress()` из PHP с теми же кириллическими значениями отработал
верно с первого раза — подтверждает, что дело не в коде.

Отдельно найдено вне скоупа этого таска (не исправлялось, только
зафиксировано): `AccountController::updateDetails()`/`updatePassword()`
(Таск 3) вызывают `requireCsrf()`, но не `requireAuth()` — гость с
валидным CSRF-токеном (доступен с любой страницы с формой, например
`/login`) получит `TypeError` на `findUserById(null)` вместо чистого
редиректа на `/login`. Не утечка данных (падение до любого чтения/
записи), но не соответствует общему паттерну `dod-global.md`
(«Незалогиненный пользователь не может открыть защищённую страницу»).
В новых методах этого таска `requireAuth()` вызывается явно во всех
пяти обработчиках.

## Задача
`FR-ACC-002` — `/account/addresses`: список сохранённых адресов с
отметкой «основной», одна форма для добавления и редактирования (по
`?edit={id}`), удаление, «сделать основным». Не более
`ACCOUNT_ADDRESSES_MAX` адресов. Закрывает `Q-DEV-002`.

## Scope — что трогаем

- [ ] `database/install.php` — изменить: таблица `addresses` (`id`,
      `user_id` FK → users ON DELETE CASCADE, `title VARCHAR(100) NULL`,
      `city VARCHAR(100) NOT NULL`, `street VARCHAR(150) NOT NULL`,
      `house VARCHAR(20) NOT NULL`, `apartment VARCHAR(20) NULL`,
      `comment VARCHAR(255) NULL`, `is_default TINYINT(1) NOT NULL
      DEFAULT 0`, `created_at`; `INDEX(user_id)`) — по образцу блока
      `favorites`
- [ ] `.docs/database.md` — изменить: новый раздел `### addresses`
      сразу после `favorites` (перенос записи из «Дополнительных
      таблиц» в основные, старая строка про `addresses` там удаляется)
- [ ] `.docs/planning-log.md` — изменить: ADR-042 (структурные поля
      вместо одного TEXT, «ровно один `is_default`» — в транзакции
      Model, не constraint БД)
- [ ] `.docs/tz-coverage.md` — изменить: `Q-DEV-002` → закрыт
- [ ] `config/config.php` — изменить: `ACCOUNT_ADDRESSES_MAX` (рядом с
      `ACCOUNT_ORDERS_PER_PAGE`)
- [ ] `src/Core/Address.php` — создать: `validateAddressInput(array
      $input): array` (город/улица/дом обязательны, длины),
      `formatAddress(array $address): string` — чистые функции
- [ ] `tests/Unit/AddressTest.php` — создать
- [ ] `tests/bootstrap.php` — изменить: подключить `Core/Address.php`
- [ ] `src/Models/Address.php` — создать: `getUserAddresses()`,
      `findUserAddress()`, `countUserAddresses()`, `createAddress()`,
      `updateAddress()`, `deleteAddress()`, `setDefaultAddress()` —
      «ровно один основной» (сброс остальных + установка) в одной
      транзакции; первый адрес — основной автоматически; удаление
      основного → основным становится самый ранний из оставшихся
- [ ] `src/Controllers/AccountController.php` — изменить: добавить
      `addresses()` (список + форма по `?edit={id}`), `storeAddress()`,
      `updateAddress(string $id)`, `deleteAddress(string $id)`,
      `setDefaultAddress(string $id)` — `requireCsrf()`,
      `requireAuth()`, ошибки — прямой рендер с `$old`/`$errors`
- [ ] `src/Views/account/addresses.php` — создать: список + форма,
      пустое состояние
- [ ] `config/routes.php` — изменить: `GET /account/addresses`,
      `POST /account/addresses`, `POST /account/addresses/{id}`,
      `POST /account/addresses/{id}/delete`,
      `POST /account/addresses/{id}/default`

## Out of scope — не трогаем

- История заказов, личные данные, избранное, СМС — Таски 2–3, 5–9
  этой же фазы
- Подстановка сохранённого адреса на `/checkout` — Таск 5 этой же фазы
- `account-sidebar.php` — ссылка на «Адреса» уже проставлена в Таске 1
- Отдельные GET-маршруты `/account/addresses/create`/`/{id}/edit` — по
  плану одна страница со списком и формой (`?edit={id}`), не как в
  `AdminCategoryController`

## Definition of Done

- [x] Добавление/редактирование/удаление → строки `addresses` с
      `user_id` текущего пользователя
- [x] Чужой `id` адреса в URL/POST → 404 или «не найден», данные не
      изменены (изоляция по `user_id`)
- [x] Пустая форма → город/улица/дом подсвечены, остальные значения
      сохранены; 419 без CSRF
- [x] Первый адрес автоматически основной; «сделать основным» снимает
      отметку с прежнего; удаление основного → основной назначен
      другому; среди адресов пользователя всегда ≤ 1 `is_default = 1`
- [x] `ACCOUNT_ADDRESSES_MAX` + 1 → flash-ограничение, запись не создана
- [x] `AddressTest`: `formatAddress()` с квартирой/без, с
      комментарием/без; валидатор — обязательные поля и длины (15 тестов)
- [x] `php database/install.php` дважды подряд — без ошибок
- [x] `composer test` зелёный (323/323)
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
