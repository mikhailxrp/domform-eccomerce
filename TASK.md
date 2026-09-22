# Current Task

## Фаза
Phase 9 — ИИ-помощники (MVP) (`.docs/phases/phase-9.md`), Таск 8 из 8
(последний по коду).

**Статус:** ✅ Завершён 22.09.2026 — проверено живым HTTP на реальной
БД, подробности в `.docs/dev-log.md`. Таск 6 подтверждён пользователем
23.09.2026 (визуальная часть DoD) — Фаза 9 закрыта целиком, это была
последняя фаза MVP.

## Задача
`/admin/ai` (только `admin`) показывает расход за текущий месяц из
`ai_requests` — всего, по классам и по помощникам, — месячный лимит и
настройки (лимит, курс USD, цена за 1000 токенов YandexGPT, тумблеры
включения трёх помощников: `specs`, `description`, `consultant`). При
превышении лимита Владелец получает предупреждение (баннер в Панели +
`logWarning()` + письмо один раз за месяц), а помощники **продолжают
работать** до ручного отключения (`Q-027`). Финальная сквозная приёмка
фазы и закрытие `Q-DEV-005`.

## Что проверено в коде перед планом
- `AI_ASSISTANTS` (`src/Core/Ai.php`) всё ещё содержит ключ `picker`,
  но с Таска 7 (`ADR-051`) подбор товара объединён с `consultant` —
  `aiComplete('picker', ...)` нигде не вызывается,
  `ai_chat_logs.assistant` пишется только как `'consultant'`. По
  решению пользователя тумблер `ai_picker_enabled` **не заводится** —
  в этом таске три тумблера (`specs`/`description`/`consultant`), не
  четыре.
- Тумблеры сейчас некому проверять: `AiChatController::consultant()`,
  `AdminAiSpecController::index()`/`run()` и
  `AdminProductController::generateDescription()` проверяют только
  `aiClassAvailable()` (есть ли ключ в `.env`) — про будущий
  `aiAssistantEnabled()` там ничего нет. Без правки этих трёх файлов
  тумблер в БД ни на что не влияет и DoD «выключенный помощник отвечает
  недоступно» не выполняется — файлы добавлены в Scope.
- `updateSettings(array $values): void` (`src/Models/Setting.php:32`)
  сейчас жёстко перебирает `array_keys(SETTING_KEYS)` — нужна
  сигнатура `updateSettings(array $values, array $allowed =
SETTING_KEYS)`, чтобы форма «Настройки» и форма «ИИ» писали каждая в
  свой whitelist ключей и не могли задеть чужие.
- `getAiRequestStats(string $month)` (`src/Models/AiUsage.php`) уже
  группирует по `assistant`/`task_class`/`provider` и считает
  `errors_count`/`spend` за месяц — вероятно, Model менять не придётся,
  разбивка по каждому измерению досчитывается в контроллере/View из уже
  возвращаемых строк.
- `aiComplete()` (`src/Services/Ai/ai.php`) после `logAiRequest()`
  сейчас не делает ничего — ни проверки лимита, ни уведомления, это
  весь функционал таска, а не правка существующей проверки.
- Пункт меню «ИИ-помощники» уже есть в `admin-header.php:32` и ведёт на
  `/admin/ai/specs` — второй пункт не заводится, `/admin/ai` вешается
  на тот же пункт (подсветка активности по префиксу `/admin/ai` уже
  работает через `str_starts_with()` в шаблоне).
- Все нужные таблицы/колонки (`ai_requests`, `settings`) уже есть в
  `database/install.php` — миграций схемы в этом таске нет.

## Scope — что трогаем
- [ ] `src/Core/Ai.php` — изменить: `AI_SETTING_KEYS`
      (`ai_monthly_limit_rub`, `ai_usd_rate`, `ai_yandex_price_per_1k`,
      `ai_specs_enabled`, `ai_description_enabled`,
      `ai_consultant_enabled`), `validateAiSettingsInput(array):
array`, `aiAssistantEnabled(string $assistant): bool` (тумблер гасит
      помощника так же, как отсутствие ключа), `isAiLimitExceeded(string
$spend, string $limit): bool`
- [ ] `src/Models/Setting.php` — изменить: `updateSettings(array
$values, array $allowed = SETTING_KEYS): void` — существующий вызов из
      `AdminSettingController::update()` не трогаем (дефолт сохраняет
      старое поведение), `/admin/ai` передаёт `AI_SETTING_KEYS`
- [ ] `src/Models/AiUsage.php` — проверить, при необходимости изменить:
      данные для `/admin/ai` (расход по классам/помощникам/ошибкам за
      месяц) уже даёт `getAiRequestStats()`
- [ ] `src/Services/Ai/ai.php` — изменить: в `aiComplete()` после
      `logAiRequest()` — проверка `isAiLimitExceeded()` по расходу
      текущего месяца, `logWarning()` и `sendMail()` Владельцу, если ещё
      не отправлено в этом месяце (метка `ai_limit_notified_month` в
      `settings`)
- [ ] `src/Controllers/AiChatController.php` — изменить: рядом с
      `aiClassAvailable(aiClassForAssistant('consultant'))` добавить
      `aiAssistantEnabled('consultant')` — при `false` тот же fallback,
      что при недоступном классе
- [ ] `src/Controllers/AdminAiSpecController.php` — изменить: в
      `index()`/`run()` добавить проверку `aiAssistantEnabled('specs')`
      — при `false` кнопка «Разобрать» неактивна, как при недоступном
      классе
- [ ] `src/Controllers/AdminProductController.php` — изменить: в
      `generateDescription()` добавить проверку
      `aiAssistantEnabled('description')` перед `aiComplete()`
- [ ] `src/Controllers/AdminAiController.php` — создать: `index()`
      (расход/лимит/настройки, `requireRole(['admin'])`), `update()`
      (POST, `requireRole(['admin'])`, `requireCsrf()`, валидация →
      `updateSettings($input, AI_SETTING_KEYS)` → flash → redirect)
- [ ] `src/Views/admin/ai/index.php` — создать: расход за месяц (всего,
      по классам, по трём помощникам, число ошибок), форма лимита/курса/
      цены, тумблеры трёх помощников
- [ ] `src/Views/emails/ai-limit-exceeded.php` — создать: письмо
      Владельцу о превышении месячного лимита
- [ ] `src/Views/layout/admin-header.php` — изменить: баннер превышения
      лимита (виден `admin`); второй пункт меню не добавляется
- [ ] `config/routes.php` — изменить: `GET /admin/ai`, `POST /admin/ai`
- [ ] `tests/Unit/AiTest.php` — изменить: тесты на
      `validateAiSettingsInput()`, `aiAssistantEnabled()`,
      `isAiLimitExceeded()`
- [ ] `.docs/phases/_status.md` — изменить: Фаза 9 → ✅ Завершена
- [ ] `.docs/tz-coverage.md` — изменить: `FR-AI-001…004`, `BR-AI-001` →
      Реализовано; `Q-DEV-005` → закрыт
- [ ] `.docs/modules/ai.md` — изменить: правка `ADR-019` (тексты из
      `content_pages`), фиксация решения не заводить `ai_picker_enabled`
- [ ] `.docs/planning-log.md` — изменить: ADR о трёх тумблерах вместо
      четырёх (снятие `picker` как отдельного управляемого помощника)
- [ ] `.docs/dev-log.md` — изменить: запись по итогам таска
- [ ] `.docs/database.md` — изменить (если понадобится): сверка с
      `database/install.php`, изменений схемы не ожидается

## Out of scope — не трогаем
- Тумблер `ai_picker_enabled` — по решению пользователя не заводится,
  подбор товара управляется тумблером `consultant`
- Автоматическое отключение помощников при исчерпании лимита — решение
  принимает Владелец вручную (`Q-027`), не автоматика
- Новая таблица/схема БД — все нужные поля уже есть
- Правки самого чат-виджета (Таск 6) кроме уже сделанного в Таске 7
- Отдельный read-only MySQL-пользователь для ИИ — не в рамках проекта
  (см. «Решения фазы» `phase-9.md`)

## Definition of Done
- [ ] Расход за месяц на `/admin/ai` совпадает с `SUM(cost_rub)` из
      `ai_requests` за тот же период (сверено `SELECT`), разбивка по
      классам и по трём помощникам сходится в сумме
- [ ] Имитация исчерпания (лимит снижен ниже текущего расхода) →
      баннер в Панели, `WARNING` в `app.log`, письмо Владельцу; **все
      три помощника продолжают работать**; второе превышение в том же
      месяце письмо не дублирует (`Q-027`)
- [ ] Тумблер «выключить» у каждого из трёх помощников по отдельности:
      `consultant` выключен → чат отвечает «недоступно» с контактами;
      `specs` выключен → кнопка «Разобрать» неактивна в очереди; 
      `description` выключен → кнопка генератора черновика скрыта/
      неактивна в форме Товара; в каждом случае остальные два продолжают
      работать
- [ ] Сквозная приёмка `AC-06` при полностью выключенном ИИ (все три
      тумблера выключены + ключи сняты): Главная, Каталог, карточка,
      поиск, корзина, `/checkout` до «Спасибо», личный кабинет, Панель —
      без ошибок; `app.log` без `ERROR`
- [ ] `NFR-AI-*` зафиксировано в `dev-log.md`: замер 10 ответов
      консультанта, время разбора пакета Товаров, проверка чистки лога
      переписки
- [ ] Секретов в БД нет — ключи провайдеров остались в `.env`
      (`Q-032`, `CLAUDE.md`)
- [ ] `manager` раздела «ИИ» не видит (пункт меню и прямой URL
      `/admin/ai` — редирект)
- [ ] POST `/admin/ai` без CSRF → 419
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
