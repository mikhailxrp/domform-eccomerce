# Current Task

## Фаза
Phase 9 — ИИ-помощники (MVP) (`.docs/phases/phase-9.md`), Таск 5 из 8.

**Статус:** ✅ Завершён 22.09.2026 — проверено живым HTTP на реальной БД
(`php -S` + `curl`), реальными ответами YandexGPT. Вопрос по тексту
`delivery-payment` («способы оплаты и размер предоплаты») → точный
ответ по документу; вопрос о сроках доставки, которых в тексте нет, →
модель не стала придумывать срок, предложила позвонить Менеджеру —
правильное поведение, не баг. Вопрос про ремонт «своего» дивана,
купленного в марте, → предложение позвонить Менеджеру с телефоном и
WhatsApp из `settings`, без попытки определить статус по описанию.
Правка `/admin/content/delivery-payment/edit` (добавлена строка про
рассрочку через тестовый банк) → следующий ответ консультанта сразу
процитировал новую строку; текст страницы возвращён в исходное
состояние после проверки (`SELECT`, длина/содержимое совпали побайтово).
Временным `error_log()` payload перед `aiComplete()` (сразу удалён
после проверки) подтверждено: в теле запроса — только системный
промпт, история сессии и текст вопроса, ни имени/телефона Покупателя,
ни данных Заказа. Вопрос в 800 символов обрезан до `AI_MAX_QUESTION_LENGTH`
(500) — проверено тем же временным логом. Класс `consultant` недоступен
(временно опустошён `AI_YANDEX_KEY`, сразу восстановлен) → JSON
`{"unavailable":true,"phone":...,"whatsapp":...}`, код 200, ровно один
`WARNING` в `app.log`. `ai_chat_logs`: строка старше 90 дней (вставлена
вручную) удалена `deleteOldAiChatLogs()`, 50 свежих тестовых строк
остались нетронуты этим вызовом — тестовые строки затем очищены
(`TRUNCATE`, реальных данных в таблице ещё не было). POST без CSRF →
419. Rate-limit: код 429 в контроллере сработал верно на 16-м быстром
запросе при лимите 15/60с (проверено пустыми вопросами — короткое
замыкание до вызова ИИ, иначе реальные вызовы медленнее декей-окна).

**Найден и исправлен по ходу проверки (в рамках Scope этого таска):**
`AiChatController.php` вызывал `setting()`, но не подключал
`src/Models/Setting.php` (только `Core/Settings.php`) — `setting()`
внутри дёргает `getAllSettings()` из Model, которую обычно загружает
`layout/header.php` при рендере страницы; у чистого JSON-эндпоинта
никакого layout нет, поэтому без явного `require_once` падал
`Fatal: Call to undefined function getAllSettings()`. Добавлен
недостающий `require_once` в `AiChatController.php`, подтверждено
успешным вызовом `setting('shop_phone')` вручную.

**Подтверждён (тем же способом, что в Таске 4) и усилен вывод по уже
известному багу `hitRateLimit()`/`tooManyAttempts()`
(`src/Core/functions.php`, вне scope): при первой попытке проверки
rate-limit'а с реальными вопросами (17 запросов подряд к настоящему
провайдеру) 429 не сработал вообще, хотя счётчик дошёл до 17 при
лимите 15 — 17 реальных вызовов заняли 135 секунд, что больше
`decaySeconds=60`, и `tooManyAttempts()` из-за незаменяемого
`first_at` посчитал окно уже истёкшим до проверки счётчика. Для
`/ai/consultant` это не гипотетический край случая, а реальный сценарий
— обычный медленный трафик реальных вопросов может полностью обходить
лимит. Не исправлено — файл вне Scope Таска 5, но теперь есть
конкретное воспроизведение на публичном эндпоинте, не только на
админском.

**Дополнение 22.09.2026 (отдельный запрос, не часть ТЗ/`FR-AI-003`):**
демо-лимит вопросов Консультанта — `LIMIT_REQUESTS=true` в `.env`
включает предел `AI_CHAT_DEMO_LIMIT = 7` вопросов на один диалог
(`chatLimitReached()`, `chatLimitReachedPayload()` в `AiChat.php`);
первый ответ диалога, пока лимит включён, начинается с приветствия
про демо-версию и модель (`buildDemoGreeting()`, реальное имя модели
из `env('AI_YANDEX_MODEL')`). Проверено живым HTTP: диалог из 9
вопросов при включённом лимите — 1–7 отвечены (первый с приветствием),
8–9 получили заглушку `{"limit_reached":true,...}` без обращения к
провайдеру; `ai_chat_logs` — ровно 14 строк (7 пар), 8-й/9-й ничего не
пишут. При `LIMIT_REQUESTS=false` — поведение как было, без лимита и
приветствия. `composer test` — 490/490 (+6 `AiChatTest`). Подробности
— `.docs/dev-log.md` 22.09.2026 (вторая запись за день). Виджету
чата (Таск 6) нужно будет обработать формат `limit_reached` наравне с
уже существующими `answer`/`unavailable`.

## Задача
`FR-AI-003` (только сервер, без виджета — он в Таске 6): `POST
/ai/consultant` принимает текст вопроса Покупателя/Гостя, собирает
системный промпт из тел страниц `content_pages` (`delivery-payment`,
`return-warranty`) и реквизитов `settings` (`shop_phone`,
`shop_whatsapp_url`), вызывает провайдера класса `user_input`
(YandexGPT) и возвращает JSON с ответом либо признаком недоступности +
контакты. В запросе к провайдеру нет имени/телефона/данных Заказа
Покупателя — только текст вопроса, история диалога текущей сессии и
системный промпт. Каждое сообщение (вопрос и ответ) пишется в
`ai_chat_logs`; записи старше 90 дней чистятся вероятностно при каждой
записи (как GC сессий). Диалог живёт в `$_SESSION`, не в личном
кабинете (`FR-AI-003` правило 7).

## Что проверено в коде перед планом
- `AI_ASSISTANTS['consultant'] = AI_CLASS_USER_INPUT` (`src/Core/Ai.php`)
  — маршрутизация на YandexGPT уже настроена, `aiComplete('consultant',
  $messages)` готов к использованию как есть.
- `findContentPageBySlug(string $slug): ?array`
  (`src/Models/ContentPage.php`) отдаёт `body` сырым текстом
  (мини-разметка `## `/`- `, не HTML) — в промпт идёт `body` напрямую,
  без `renderContentBody()` (та функция только для витрины).
- `setting('shop_phone')` / `setting('shop_whatsapp_url')`
  (`src/Core/Settings.php`) — готовые геттеры реквизитов с кэшем на
  один запрос.
- Образец JSON-эндпоинта — `SearchController::suggest()`:
  `Content-Type: application/json`, `tooManyAttempts()`/
  `hitRateLimit()`, `JSON_UNESCAPED_UNICODE`.
- Образец вызова модели — `AdminAiSpecController::run()`/
  `aiComplete()` (`src/Services/Ai/ai.php`): `aiComplete()` сам логирует
  `ERROR` и пишет `ai_requests` при сбое вызова; когда класс просто не
  настроен (`aiClassAvailable() === false`), `aiComplete()` возвращает
  `null` **без** лога — значит `WARNING` при недоступности (пункт DoD)
  пишет сам контроллер, проверив `aiClassAvailable()` заранее, а не
  полагаясь на `aiComplete()`.
- `ai_requests`/`ai_spec_suggestions` в `database/install.php` —
  образец идемпотентного `CREATE TABLE IF NOT EXISTS` без FK (для
  лога, где нет строгой связи с одной сущностью) — `ai_chat_logs`
  создаётся тем же способом.
- `tests/bootstrap.php` уже подключает `Core/Ai.php`, `Core/AiSpecs.php`,
  `Core/AiDescription.php` — `Core/AiChat.php` добавляется туда же.
- Публичный CSRF-эндпоинт без `requireRole()` — образец
  `CheckoutController`/`ReviewController` (`tooManyAttempts()` с более
  жёсткими лимитами, чем у Панели, т.к. эндпоинт открыт анонимам).

## Scope — что трогаем
- [ ] `database/install.php` — изменить: таблица `ai_chat_logs` (`id`,
      `conversation_id CHAR(32)`, `assistant ENUM('consultant','picker')`,
      `role ENUM('user','assistant')`, `message TEXT`, `created_at`,
      `INDEX(conversation_id)`, `INDEX(created_at)`), без FK — по
      образцу `ai_requests`
- [ ] `.docs/database.md` — изменить: раздел таблицы `ai_chat_logs`
- [ ] `.docs/planning-log.md` — изменить: `ADR-050` (правка `ADR-019` —
      правила берутся из `content_pages` при каждом запросе вместо
      переноса вручную в промпт один раз; retrieval/база знаний
      по-прежнему не реализуются)
- [ ] `src/Core/AiChat.php` — создать, чистые функции:
      `buildConsultantPrompt(array $pages, array $contacts): string` —
      системный промпт: круг тем (доставка/оплата/возврат/гарантия),
      запрет придумывать статус конкретного Заказа/отвечать по общим
      знаниям, инструкция предлагать звонок Менеджеру с
      `$contacts['phone']`/`$contacts['whatsapp']` вне круга тем;
      `normalizeChatQuestion(string $question): string` — `trim`,
      обрезка до `AI_MAX_QUESTION_LENGTH`; `trimChatHistory(array
      $history): array` — последние `AI_CHAT_HISTORY_LIMIT` сообщений;
      `chatFallbackPayload(array $contacts): array` — `['unavailable'
      => true, 'phone' => ..., 'whatsapp' => ...]`
- [ ] `src/Models/AiChatLog.php` — создать: `logAiChatMessage(string
      $conversationId, string $assistant, string $role, string
      $message): void`, `deleteOldAiChatLogs(int $days): int`,
      `maybeCleanupAiChatLogs(): void` (вероятностный вызов чистки,
      `1 / AI_CHAT_LOG_GC_DIVISOR`, по образцу GC сессий)
- [ ] `src/Controllers/AiChatController.php` — создать: `consultant()`
      — JSON, `requireCsrf()`, `tooManyAttempts('ai_chat', 15, 60)`/
      `hitRateLimit()`, `conversation_id` в `$_SESSION` (генерируется
      один раз на сессию), история в
      `$_SESSION['ai_consultant_history']`, недоступный класс →
      `logWarning()` + `chatFallbackPayload()` с кодом 200, успех →
      `logAiChatMessage()` дважды (`user`/`assistant`) +
      `maybeCleanupAiChatLogs()`
- [ ] `config/routes.php` — изменить: `POST /ai/consultant` (без
      `requireRole` — доступно Гостю и Покупателю)
- [ ] `config/config.php` — изменить: `AI_MAX_QUESTION_LENGTH`,
      `AI_CHAT_HISTORY_LIMIT`, `AI_CHAT_LOG_RETENTION_DAYS` (90),
      `AI_CHAT_LOG_GC_DIVISOR`
- [ ] `tests/Unit/AiChatTest.php` — создать: тесты на
      `normalizeChatQuestion()` (обрезка, `trim`), `trimChatHistory()`
      (оставляет последние N), `buildConsultantPrompt()` (промпт
      содержит переданные контакты и тексты страниц),
      `chatFallbackPayload()` (форма ответа)
- [ ] `tests/bootstrap.php` — изменить: подключить `src/Core/AiChat.php`

## Out of scope — не трогаем
- Виджет чата на витрине, CSS/JS, предупреждение о личных данных —
  Таск 6
- Подбор Товара диалогом (`POST /ai/picker`) — Таск 7
- Бюджет/лимит, тумблеры включения помощников, `/admin/ai` — Таск 8
- CRUD `content_pages`, `/admin/content/*` — уже сделаны в Фазе 8, не
  трогаются
- Отображение истории диалога в личном кабинете — по правилу
  `FR-AI-003`/`FR-AI-004` история никогда туда не попадает

## Definition of Done
- [ ] `curl` с вопросом про сроки доставки → ответ по тексту страницы
      `delivery-payment`, без даты/номера конкретного Заказа
- [ ] Вопрос про гарантийный ремонт купленного дивана → ответ
      предлагает позвонить Менеджеру, содержит телефон и ссылку
      WhatsApp из `settings`, не пытается определить статус по
      номеру/имени
- [ ] Правка текста страницы `/admin/content/delivery-payment/edit`
      меняет следующий ответ консультанта без правки кода
- [ ] В теле запроса к провайдеру — только системный промпт, история
      текущей сессии и текст вопроса: ни имени, ни телефона
      Покупателя, ни данных Заказа (проверено временным логированием
      payload, лог удалён после проверки)
- [ ] Вопрос длиннее `AI_MAX_QUESTION_LENGTH` обрезается до отправки
      провайдеру
- [ ] Класс `consultant` недоступен (ключ снят) → JSON `{"unavailable":
      true, "phone": ..., "whatsapp": ...}`, код 200, ровно один
      `WARNING` в `app.log`
- [ ] Строки пишутся в `ai_chat_logs` (по одной на вопрос и на ответ,
      общий `conversation_id`); строка с `created_at` старше 90 дней
      (вставлена вручную) удаляется чисткой, свежие остаются
- [ ] POST без CSRF → 419; превышение частоты → 429
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

## Важные правила
- Следовать `CLAUDE.md`
- Работать только в рамках Scope
- Не менять файлы вне Scope
- Не рефакторить попутно
