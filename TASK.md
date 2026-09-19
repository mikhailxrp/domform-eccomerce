# Current Task

## Фаза
Вне фазы — внеплановая задача заказчика (`ADR-039`, `.docs/planning-log.md`,
`.docs/dev-log.md` 19.09.2026). Не пункт ТЗ, к `.docs/phases/phase-N.md`
не привязана. Фаза 4 (`.docs/phases/phase-4.md`) при этом уже
✅ Завершена — Таск 10 сдан 19.09.2026, «Закрытие фазы» ещё не доведено
(см. `dev-log.md`).

**Статус:** ✅ Завершена. Реализовано и проверено на реальной БД
(`mikhail700.beget.tech`) живой HTTP-сессией через `php -S` + `curl`.
`composer test` 226/226 (без изменений — новых Model-функций к БД в
юнит-тестах нет намеренно, `php.md`, «Тестирование»).

## Задача
Демо-раздел Панели управления (`manager`+`admin`), тот же приём, что
страница-заглушка оплаты (`ADR-018`) — показать возможность, не
реализовать интеграцию:
- `/admin/sales-channels` («Каналы продаж») — переключатели WhatsApp /
  Авито / Telegram / MAX / сайт (сайт — заблокированный, всегда
  включённый пункт) + статичный макет «единого чата» + пояснение, что
  это демо-версия
- `/admin/integrations` («Интеграции») — переключатели CRM
  (Битрикс24/amoCRM), учёта (1С/МойСклад), телефонии, рассылок +
  пояснение, что это демо-страница

Состояние переключателей сохраняется в новых таблицах
`sales_channels`/`integrations` (подтверждено пользователем —
`AskUserQuestion` перед кодом), но реальных подключений не выполняет.

## Scope — что трогаем

- [x] `database/install.php` — изменить: таблицы `sales_channels`,
      `integrations` + сид `INSERT IGNORE` (демо-набор каналов/интеграций)
- [x] `.docs/database.md` — изменить: обе таблицы, `ADR-039`
- [x] `.docs/planning-log.md` — изменить: строка `ADR-039`
- [x] `src/Models/SalesChannel.php` — создать: `getSalesChannels()`,
      `updateSalesChannels(array $enabledCodes): void` — обновляет
      только реально существующие и не заблокированные (`is_locked`) коды
- [x] `src/Models/Integration.php` — создать: `getIntegrations()`,
      `updateIntegrations(array $enabledCodes): void`
- [x] `src/Controllers/AdminSalesChannelController.php` — создать:
      `index()`, `update()` — `requireRole(['manager','admin'])`,
      `requireCsrf()`
- [x] `src/Controllers/AdminIntegrationController.php` — создать:
      `index()` (группировка по `category`), `update()`
- [x] `src/Views/admin/sales-channels/index.php` — создать: список
      переключателей + статичный (захардкоженный, не из БД) макет
      «единого чата» + текст о демо-версии
- [x] `src/Views/admin/integrations/index.php` — создать: список
      переключателей по группам + текст о демо-странице
- [x] `src/Views/layout/admin-header.php` — изменить: два новых пункта
      сайдбара
- [x] `config/routes.php` — изменить: `GET`/`POST /admin/sales-channels`,
      `GET`/`POST /admin/integrations`

## Out of scope — не трогаем

- Реальная интеграция с любым из перечисленных каналов/сервисов —
  вся страница декларативно демонстрационная
- `.docs/phases/_status.md` / нумерация Фаз — задача не входит ни в
  одну Фазу ТЗ
- «Закрытие Фазы 4» (`tz-coverage.md`/`admin-assembly.md`/`_status.md`)
  — отдельная, ранее не доведённая задача, эта её не подменяет

## Definition of Done

- [x] `install.php` дважды подряд без ошибок; обе таблицы и сид на
      месте — проверено
- [x] Гость по обоим маршрутам (GET и POST) → редирект `/login`;
      `customer` → редирект `/` (проверено подстановкой `user_id` в
      файл PHP-сессии, реальный пароль сидового аккаунта не хранится в
      репозитории)
- [x] `admin`/`manager` видят обе страницы (200), ни одного `http://`
      в HTML — проверено под `admin` (пароль из `.env`)
- [x] Переключатель канала/интеграции сохраняется в БД; `website`
      (`is_locked=1`) не меняется даже прямым POST; несуществующий код
      в `channels[]`/`integrations[]` не создаёт строку и не вызывает
      ошибку — все три случая проверены на реальной БД
- [x] POST без `_csrf` → 419
- [x] `composer test` зелёный — 226/226, без регрессий
- [x] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
