# Phase 9 — ИИ-помощники (MVP)

## Цель

Администратор разбирает характеристики Товаров из текста описания
пакетом и подтверждает каждое предложенное значение перед публикацией,
генерирует черновик описания Товара по кратким данным; Гость и
Покупатель задают вопросы консультанту в чате по доставке, оплате,
возврату и гарантии и подбирают Товар свободным текстом из реальных
карточек каталога; расход на ИИ виден в Панели управления с месячным
лимитом и предупреждением Владельцу. Недоступность или ручное
отключение провайдера гасит конкретного помощника и никогда не мешает
посмотреть каталог и оформить Заказ — `FR-AI-001…004`, `BR-AI-001`,
`NFR-AI-*`, `AC-06`.

Не входит: `FR-AI-005` (SEO-тексты Категорий) и `FR-AI-006` (смысловой
поиск) — 2-я очередь, раздел 3.2 ТЗ; «Помощник менеджера» и «Фильтр
отзывов» — бриф не раскрывает содержание, требований в ТЗ нет и модуль
их не покрывает (`.docs/modules/ai.md`); база знаний/retrieval по
страницам `CNT` (`ADR-019` — см. правку в «Решениях фазы»); хранение
диалога между визитами и связь диалога с личным кабинетом
(`FR-AI-004` правило 4); автоматическое отключение помощников при
исчерпании лимита (`Q-027` — решение принимает Владелец).

## Статус

🔄 В работе

## Что уже готово (на чём строим)

- **Внешних HTTP-вызовов в проекте ещё не было.** `src/Services/`
  содержит `Mailer.php`, `Sms.php`, `FileUpload.php` — все локальные.
  Эта фаза первая вводит исходящий запрос к внешнему API, поэтому
  таймаут, обработка отказа и журнал вызовов — часть Таска 1, а не
  «потом добавим».
- `src/Controllers/SearchController.php::suggest()` — готовый образец
  JSON-endpoint витрины: `Content-Type: application/json`,
  `tooManyAttempts()`/`hitRateLimit()`, `http_response_code(429)`,
  `JSON_UNESCAPED_UNICODE`. Оба чат-endpoint'а строятся по нему.
- `setting()` (`src/Core/Settings.php`, кэш на один запрос) + таблица
  `settings` — сюда же ложатся настройки ИИ (лимит, курс, тарифы,
  тумблеры помощников). `SETTING_KEYS` — whitelist формы реквизитов;
  для ИИ заводится отдельный `AI_SETTING_KEYS`, чтобы форма
  «Настройки» и форма «ИИ» не писали в чужие ключи.
- `content_pages` со строками `delivery-payment` и `return-warranty`
  (Таск 1 Фазы 8) — источник правил для системного промпта
  консультанта; `renderContentBody()` нужен витрине, промпту идёт
  сырое `body`.
- `requireRole(['admin'])` и фильтрация пунктов сайдбара по ролям
  (`$adminNavItems`, ключ `roles` в
  `src/Views/layout/admin-header.php`) — готовы с Фазы 8, раздел
  «Настройки» — образец `admin`-only экрана.
- `product_specs` (`FR-CARD-005`), `product_variants.material`/
  `mechanism_type`, `variant_images.color` — то, куда пишет разбор
  характеристик и откуда берётся «справочник допустимых значений»
  (см. «Решения фазы»).
- Форма Товара в Панели (`src/Views/admin/products/form.php`,
  `src/Core/ProductForm.php`, `normalizeProductSpecs()`) — сюда
  встраиваются кнопка генерации описания и бейдж статуса разбора.
- `public/assets/js/app.js` (витрина) и `admin.js` (Панель) — два
  IIFE-файла на два layout (`ADR-036`); виджет чата живёт в `app.js`,
  кнопки генерации — в `admin.js`.
- `sendMail()` (`src/Services/Mailer.php`) + шаблоны
  `src/Views/emails/` — для письма Владельцу при исчерпании лимита.
- Виджета чата в купленной теме нет (проверено: ни один макет
  `00-input/design/*.html` его не содержит) — рисуется в стиле темы,
  тот же случай, что страницы `CNT` в Фазе 8 (`Q-012`).

## Решения фазы

- **Провайдер выбирается по классу задачи, а не по помощнику**
  (`BR-AI-001` правило 3, ADR Таска 1). Класс «обезличенные данные»
  (`FR-AI-001`, `FR-AI-002`) → Claude через **OpenRouter**; класс
  «пользовательский ввод» (`FR-AI-003`, `FR-AI-004`) → **YandexGPT**
  (российский провайдер, правило 2 `BR-AI-001`). Смена провайдера или
  модели — значение в `.env`, не правка кода. Это ровно тот случай,
  когда `php.md` разрешает классы вместо функций («несколько
  взаимозаменяемых провайдеров за одним контрактом»): интерфейс
  `AiProvider` + по классу на провайдера, наружу — одна функция
  `aiComplete()`.
- **Класс без настроенного ключа = класс недоступен.** Запрос не
  уходит молча к провайдеру другой юрисдикции:
  `aiClassAvailable()` возвращает `false`, помощник показывает
  «недоступно» (`AC-06`). Поэтому фазу можно вести и проверять, имея
  настроенным один ключ из двух.
- **Модель никогда не имеет доступа к БД — ни на чтение, ни на
  запись.** Это архитектурная гарантия интерфейса, а не соглашение
  между разработчиками: `AiProvider::complete()` (Таск 1) принимает
  только массив сообщений (текст) и не получает `PDO`/DB-credentials
  ни при вызове, ни через конструктор — файлы `src/Services/Ai/*`
  не подключают `Core/Database.php` и не могут физически выполнить
  SQL. Все данные, которые попадают в промпт, заранее выбирает наш
  собственный код через обычные `SELECT`-функции Model (`Таск 2` —
  `getKnownSpecValues()`, `Таск 5` — чтение `content_pages`, `Таск 7`
  — `getConfirmedProductsForAi()`) — модель их только читает как текст,
  повлиять на сам запрос не может. Запись результата ИИ в БД идёт
  только через отдельные, специально написанные Model-функции с
  проверкой/подтверждением (`applySpecSuggestions()` — только после
  явного ревью Администратором, Таск 3; `extractPickedSlugs()` —
  каждый slug сверяется с БД перед показом, Таск 7; `ai_chat_logs`
  пишет наш контроллер, а не модель, Таск 5) — модель никогда не
  выполняет запись сама, максимум её текст становится входом для уже
  существующей, проверяемой цепочки записи. Отдельный read-only
  MySQL-пользователь для этого не заводится — на shared-хостинге это
  дополнительный ручной шаг в панели хостинга, а границу и так держит
  сам интерфейс `AiProvider`, у которого просто нет параметра для
  подключения к БД.
- **Стоимость считается по-разному у двух провайдеров, наружу отдаётся
  одинаково.** OpenRouter возвращает в ответе `usage.cost` —
  фактическую стоимость запроса в USD (своя таблица тарифов не нужна),
  переводится в рубли по настройке `ai_usd_rate`. YandexGPT возвращает
  только токены (`inputTextTokens`/`completionTokens`) — рубли
  считаются по настройке `ai_yandex_price_per_1k`. Провайдер отдаёт
  нормализованный массив
  `['text', 'tokens_in', 'tokens_out', 'cost_rub']`, различие не
  протекает выше `src/Services/Ai/`.
- **Консультант читает `content_pages` из БД при каждом запросе — это
  правка `ADR-019`** (ADR Таска 5). `ADR-019` фиксировал ручной перенос
  правил доставки/оплаты/возврата/гарантии в текст системного промпта,
  когда таблицы `content_pages` ещё не существовало; после Фазы 8 те же
  тексты редактируются Администратором в Панели, и ручная синхронизация
  промпта — прямой долг, отмеченный в `.docs/modules/ai.md`. Два
  `SELECT` (`delivery-payment`, `return-warranty`) плюс реквизиты из
  `settings` — это не база знаний и не retrieval: ограничение «без
  поиска по живым страницам, без векторов, без FAQ-индекса» остаётся в
  силе, круг тем по-прежнему задан промптом.
- **«Справочник допустимых значений Категории» (`FR-AI-001` правило 3)
  выводится из существующих данных каталога**, новых таблиц-справочников
  не заводится (ADR Таска 2): допустимые значения — `DISTINCT` по
  `product_specs.name`/`value`, `product_variants.material`,
  `product_variants.mechanism_type`, `variant_images.color` в пределах
  Категорий Товара. Значение, которого в каталоге ещё нет, помечается
  «требует решения» и не записывается как есть — Администратор либо
  правит его, либо принимает как новое допустимое значение. Отдельная
  таблица справочника по Категориям отклонена: ТЗ такого экрана не
  требует, а пустой справочник на старте сделал бы «требует решения»
  вообще каждое предложение.
- **Куда пишется подтверждённое предложение.** Предложение несёт цель
  (`target`): `spec` → строка в `product_specs`;
  `variant_material`/`variant_mechanism` → поле выбранного
  Администратором Варианта (`product_variants`), потому что материал и
  механизм в схеме принадлежат Варианту, а не Товару (`ADR-004`);
  `color` — **информационное**, автоматически не записывается нигде:
  цвет в схеме это атрибут фотографии Варианта (`ADR-006`), его задаёт
  загрузка фото. Дублировать материал/механизм/цвет строками
  `product_specs` нельзя — `database.md` прямо фиксирует, что
  `product_specs` их не хранит.
- **ИИ-действия Панели — только `admin`** (`requireRole(['admin'])`):
  роль в `FR-AI-001`/`FR-AI-002` — Администратор. Менеджер видит
  характеристики и описание и правит их руками как раньше, но кнопок
  «Разобрать»/«Сгенерировать» и раздела «ИИ» не видит — тот же приём,
  что «Настройки»/«Сотрудники» в Фазе 8.
- **Публичные endpoint'ы стоят денег, поэтому ограничиваются на
  сервере:** `requireCsrf()` (POST из своей же сессии), лимит длины
  вопроса (`AI_MAX_QUESTION_LENGTH`), лимит сообщений в диалоге
  (`AI_CHAT_HISTORY_LIMIT`), `tooManyAttempts()` по образцу
  `suggest()`. Дебаунс на клиенте не защищает от скриптовой накрутки —
  формулировка принята ещё в Фазе 1.
- **Модель не выбирает, что показать Покупателю — сервер проверяет.**
  Ответ подбора (`FR-AI-004`) разбирается как список slug'ов, каждый
  slug проверяется по БД; несуществующий отбрасывается, карточки
  рендерятся из данных БД, а не из текста модели (`FR-AI-004`
  правило 2). Формат ответа модели — JSON, разбор через
  `decodeAiJson()` с юнит-тестами на мусор, обрамление ` ```json `
  и пустой ответ.
- **Лог переписки — 3 месяца** (`NFR-AI-*`, `Q-028`): чистка
  вероятностная при записи (1 к `AI_CHAT_LOG_GC_DIVISOR`, как GC
  сессий) — на shared-хостинге cron может не быть, а кнопка «почистить»
  в Панели зависит от того, зайдёт ли туда кто-то вовремя.
- **Именованные константы** — `AI_TIMEOUT_SECONDS`,
  `AI_MAX_QUESTION_LENGTH`, `AI_CHAT_HISTORY_LIMIT`,
  `AI_CHAT_LOG_RETENTION_DAYS` (90), `AI_CHAT_LOG_GC_DIVISOR`,
  `AI_SPECS_BATCH_MAX`, `AI_PICKER_RESULTS` (3),
  `AI_CATALOG_SNAPSHOT_LIMIT`, `ADMIN_AI_SPECS_PER_PAGE` — в
  `config/config.php` по образцу Фаз 4–8; константы, нужные чистым
  функциям `src/Core/Ai*.php`, — в самих этих файлах
  (`tests/bootstrap.php` не подключает `config.php`).

## Таски

| #   | Название                                                                      | Статус      |
| --- | ----------------------------------------------------------------------------- | ----------- |
| 1   | Ядро ИИ: провайдеры по классу задачи, вызов с таймаутом, журнал `ai_requests` | ✅ Завершён |
| 2   | Разбор характеристик: схема, очередь «требует разбора», пакетный запуск       | ✅ Завершён |
| 3   | Ревью и подтверждение предложений, статус «характеристики подтверждены»       | ✅ Завершён |
| 4   | Генератор черновика описания Товара                                           | ⏳ Ожидает  |
| 5   | Консультант в чате: промпт из `content_pages`, endpoint, лог 3 месяца         | ⏳ Ожидает  |
| 6   | Виджет чата на витрине и предупреждение о личных данных                       | ⏳ Ожидает  |
| 7   | Подбор Товара диалогом                                                        | ⏳ Ожидает  |
| 8   | Расход, месячный лимит, ручное отключение + приёмка `AC-06`/`NFR-AI-*`        | ⏳ Ожидает  |

Порядок по зависимостям: ядро → помощники Панели (они наполняют
подтверждённые характеристики, без которых подбор пуст — «Порядок
задач» раздела 8.19 ТЗ) → чат → подбор → бюджет и приёмка.

---

## Таск 1 — Ядро ИИ: провайдеры по классу задачи, вызов с таймаутом, журнал `ai_requests`

**Статус:** ✅ Завершён 22.09.2026 — подробности проверки в `TASK.md`
и `.docs/dev-log.md`.

**Цель таска:**
Появляется единственная точка вызова модели —
`aiComplete(string $taskClass, array $messages, array $options): ?array`,
которая выбирает провайдера по классу задачи из конфигурации
(`BR-AI-001` правило 3), делает HTTP-запрос с таймаутом, пишет строку в
`ai_requests` (провайдер, класс, токены, стоимость в рублях, статус,
длительность) и возвращает `null` при любой ошибке, отсутствии ключа
или таймауте — не бросая исключение наружу. Помощников ещё нет,
проверяется временным CLI-скриптом.

**Что нужно создать/изменить:**

- `database/install.php` — изменить: таблица `ai_requests`
  (`provider VARCHAR(30)`, `task_class VARCHAR(20)`,
  `assistant VARCHAR(30)`, `tokens_in INT`, `tokens_out INT`,
  `cost_rub DECIMAL(10,4)`, `status ENUM('ok','error')`,
  `error VARCHAR(255) NULL`, `duration_ms INT`, `created_at`),
  `INDEX(created_at)` — расход считается за календарный месяц; сид
  настроек `ai_monthly_limit_rub` (5000), `ai_usd_rate`,
  `ai_yandex_price_per_1k` через `INSERT IGNORE`;
  `.docs/database.md` — раздел таблицы; `.docs/planning-log.md` —
  ADR-048 (маршрутизация по классу задачи, два провайдера,
  недоступность класса вместо тихой подмены)
- `src/Core/Ai.php` — создать, чистые функции:
  `AI_CLASS_ANONYMOUS`/`AI_CLASS_USER_INPUT`, `AI_ASSISTANTS`
  (`specs`/`description`/`consultant`/`picker` → класс),
  `aiClassForAssistant()`, `decodeAiJson(string): ?array` (снимает
  ` ``` `-обрамление, отсекает мусор),
  `costRubFromUsd(float $usd, string $rate): string` и
  `costRubFromTokens(int $in, int $out, string $pricePer1k): string`
  (`bcmath`, как `discountedPrice()` в `Core/Price.php` — деньги не
  `float`)
- `src/Services/Ai/AiProvider.php` — создать: интерфейс
  `complete(array $messages, array $options): array` (возврат —
  `['text', 'tokens_in', 'tokens_out', 'cost_rub']`); рядом
  `src/Services/Ai/AiHttp.php` — один cURL-вызов (таймаут
  `AI_TIMEOUT_SECONDS`, без ретраев — ответ нужен за ≤10 с,
  `NFR-AI-*`)
- `src/Services/Ai/OpenRouterProvider.php` — создать:
  `POST https://openrouter.ai/api/v1/chat/completions`,
  `Authorization: Bearer`, модель из `.env`; стоимость — из
  `usage.cost` (USD) по курсу `ai_usd_rate`
- `src/Services/Ai/YandexGptProvider.php` — создать:
  `POST https://ai.api.cloud.yandex.net/foundationModels/v1/completion`,
  `Authorization: Api-Key`, `modelUri: gpt://<folder_id>/<model>`;
  стоимость — из токенов по `ai_yandex_price_per_1k`
- `src/Services/Ai/ai.php` — создать:
  `providerFor(string $taskClass): ?AiProvider` (чтение `.env`),
  `aiClassAvailable(string): bool`, `aiComplete()` — вызов +
  `logAiRequest()` + `logError()` при отказе
- `src/Models/AiUsage.php` — создать: `logAiRequest(array): void`,
  `getAiSpendForMonth(string $month): string`,
  `getAiRequestStats(string $month): array`
- `tests/Unit/AiTest.php` — создать; `tests/bootstrap.php` — подключить
  `src/Core/Ai.php`
- `config/config.php` — изменить: `AI_TIMEOUT_SECONDS`;
  `.env.example` — изменить: `AI_PROVIDER_ANONYMOUS`,
  `AI_OPENROUTER_KEY`, `AI_OPENROUTER_MODEL`, `AI_PROVIDER_USER_INPUT`,
  `AI_YANDEX_KEY`, `AI_YANDEX_FOLDER_ID`, `AI_YANDEX_MODEL`

**Definition of Done:**

- [ ] Временный CLI-скрипт: реальный вызов каждого настроенного
      провайдера возвращает текст; в `ai_requests` появилась строка с
      ненулевыми `tokens_in`/`tokens_out`, `cost_rub > 0` и заполненным
      `duration_ms` (проверено `SELECT`)
- [ ] Битый ключ, несуществующий хост и искусственный таймаут
      (сниженный `AI_TIMEOUT_SECONDS`) → `aiComplete()` вернул `null`,
      в `storage/logs/app.log` `ERROR`, строка в `ai_requests` со
      `status='error'`, исключение наружу не вышло
- [ ] Ключ класса не задан → `aiClassAvailable()` = `false`,
      `aiComplete()` = `null`, HTTP-запрос не отправлялся
- [ ] `decodeAiJson()`: чистый JSON, JSON в ` ```json `, текст
      без JSON, пустая строка — юнит-тесты; расчёт рублей из USD и из
      токенов — юнит-тесты (`bcmath`, без `float`)
- [ ] Ключи и секреты только в `.env` — ни в репозитории, ни в БД
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 2 — Разбор характеристик: схема, очередь «требует разбора», пакетный запуск

**Статус:** ✅ Завершён 22.09.2026 — подробности проверки в `TASK.md` и
`.docs/dev-log.md`.

**Цель таска:**
`FR-AI-001` правила 1, 2, 5, 6: Администратор открывает
`/admin/ai/specs`, видит очередь Товаров со статусом «требует разбора»,
отмечает несколько и запускает разбор **пакетом**. Предложенные
значения сохраняются в `ai_spec_suggestions` со статусом `ok` или
`needs_decision`; в карточку Товара не пишется ничего, `description`
не меняется. Экран подтверждения — следующий таск.

**Что нужно создать/изменить:**

- `database/install.php` — изменить: `products.specs_status
ENUM('pending','confirmed') NOT NULL DEFAULT 'pending'` +
  `INDEX(specs_status)`; таблица `ai_spec_suggestions`
  (`product_id` FK CASCADE, `target ENUM('spec','variant_material',
'variant_mechanism','color')`, `name VARCHAR(100)`,
  `value VARCHAR(255)`, `status ENUM('ok','needs_decision')`,
  `created_at`, `INDEX(product_id)`); добавление колонки и индекса —
  идемпотентно через `information_schema` (приём `ADR-031`/`ADR-037`);
  `.docs/database.md`; `.docs/planning-log.md` — ADR-049 (справочник из
  существующих данных, цели предложений, `specs_status`)
- `src/Core/AiSpecs.php` — создать, чистые:
  `buildSpecsPrompt(array $product, array $knownValues): array`,
  `normalizeSpecSuggestions(array $decoded, array $knownValues): array`
  — отбрасывает пустые значения (правило 2: не подставлять по
  умолчанию), режет длину, помечает `needs_decision` для значений вне
  известных (правило 3), схлопывает дубли
- `src/Models/AiSpec.php` — создать:
  `getProductsForSpecsQueue(string $status, int $page, int $perPage)`,
  `countProductsForSpecsQueue(string $status)`,
  `getKnownSpecValues(array $categoryIds): array` (`DISTINCT` по
  `product_specs`, `product_variants.material`/`mechanism_type`,
  `variant_images.color`), `replaceSpecSuggestions(int $productId,
array $rows): void` (транзакция: удалить прежние предложения Товара,
  вставить новые)
- `src/Controllers/AdminAiSpecController.php` — создать: `index()`
  (очередь + пагинация), `run()` (POST, `requireCsrf()`, до
  `AI_SPECS_BATCH_MAX` Товаров за раз, по Товару — `aiComplete()`,
  неответившие пропускаются с flash «разобрано N из M»), оба —
  `requireRole(['admin'])`
- `src/Views/admin/ai/specs/index.php` — создать: список Товаров
  (чекбоксы, статус, число предложений), кнопка «Разобрать выбранные»;
  при недоступном классе — алерт вместо кнопки
- `src/Views/layout/admin-header.php` — изменить: пункт «ИИ-помощники»
  с `roles => ['admin']`
- `config/routes.php` — изменить: `GET /admin/ai/specs`,
  `POST /admin/ai/specs/run`
- `tests/Unit/AiSpecsTest.php` — создать; `tests/bootstrap.php` —
  подключить

**Definition of Done:**

- [ ] Описание «диван раскладной, обивка — рогожка бежевая» → в
      `ai_spec_suggestions` появились предложения по механизму и цвету
      (проверено `SELECT` на реальной БД)
- [ ] Описание без размера → строки «размер» нет вовсе (не пустое
      значение), Товар не помечен ошибочным
- [ ] Значение, которого нет в каталоге → `status='needs_decision'`
      (юнит-тест `normalizeSpecSuggestions()` + проверка на живом
      разборе)
- [ ] `products.description` после разбора не изменился (`SELECT` до и
      после), `product_specs` не пополнились
- [ ] Повторный разбор того же Товара заменяет прежние предложения, не
      дублирует их
- [ ] Класс недоступен (ключ снят) → кнопка «Разобрать» не активна,
      очередь открывается, характеристики вводятся вручную в форме
      Товара (`FR-AI-001`, «при недоступности»)
- [ ] POST без CSRF → 419; `manager` на `/admin/ai/specs` → редирект на
      `/admin`, пункта в сайдбаре нет
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 3 — Ревью и подтверждение предложений, статус «характеристики подтверждены»

**Статус:** ✅ Завершён 22.09.2026 — подробности проверки в `TASK.md`
и `.docs/dev-log.md`. Найденный по ходу риск в
`AdminAiSpecController::run()` (Таск 2) — тесный таймаут-бюджет,
однажды давший `Fatal: Maximum execution time exceeded` при разборе
одного Товара — исправлен тем же днём отдельной правкой:
`AI_SPECS_BATCH_OVERHEAD_SECONDS` (20 c, `config/config.php`) вместо
захардкоженных `+5`, бюджет на Товар вырос с 20 до 35 c. Подробности —
`.docs/dev-log.md` 22.09.2026.

**Цель таска:**
`FR-AI-001` правила 3–5: на `/admin/ai/specs/{id}` Администратор видит
предложения по Товару, каждое может принять, поправить или отклонить;
принятые пишутся в `product_specs` (цель `spec`) и в поля выбранного
Варианта (цели `variant_material`/`variant_mechanism`), `color` —
только подсказка. После подтверждения Товар получает
`specs_status='confirmed'` — это и есть допуск в подбор (`FR-AI-004`).
Тот же статус Администратор может поставить вручную, вообще не запуская
ИИ.

**Что нужно создать/изменить:**

- `src/Core/AiSpecs.php` — изменить: `validateSpecReviewInput(array):
array` — принятое `needs_decision` без правки значения не проходит;
  цель `variant_*` требует выбранного `variant_id`; пустое значение
  принять нельзя
- `src/Models/AiSpec.php` — изменить: `applySpecSuggestions(int
$productId, array $accepted): bool` — одна транзакция: `INSERT` в
  `product_specs` без дублей по `name`, `UPDATE product_variants`,
  `UPDATE products SET specs_status='confirmed'`, удаление обработанных
  предложений; `setProductSpecsStatus(int $productId, string $status)`
- `src/Controllers/AdminAiSpecController.php` — изменить:
  `review(string $id)`, `apply(string $id)` (POST, `requireCsrf()` →
  при ошибке прямой рендер с `$old`/`$errors`, при успехе `redirect()`
  - flash), `confirmManually(string $id)`
- `src/Views/admin/ai/specs/review.php` — создать: таблица предложений
  (цель, название, значение, бейдж «требует решения»), поле правки,
  выбор Варианта для `variant_*`, кнопки «Применить»/«Отклонить»/
  «Подтвердить вручную»
- `src/Views/admin/products/form.php` — изменить: бейдж статуса
  характеристик и ссылка на разбор (ссылка — только `admin`)
- `config/routes.php` — изменить: `GET /admin/ai/specs/{id}`,
  `POST /admin/ai/specs/{id}/apply`, `POST /admin/ai/specs/{id}/confirm`
- `tests/Unit/AiSpecsTest.php` — изменить

**Definition of Done:**

- [ ] Принято 2 предложения из 3 → в `product_specs` ровно 2 новые
      строки, отклонённого нет нигде, `specs_status='confirmed'`
      (проверено `SELECT`)
- [ ] Предложение `variant_material` применено к выбранному Варианту →
      изменился именно он, остальные Варианты Товара не тронуты
- [ ] `needs_decision` без правки значения принять нельзя — поле
      подсвечено, в БД ничего не записано
- [ ] Повторное применение того же набора не создаёт дублей в
      `product_specs`
- [ ] «Подтвердить вручную» без единого предложения (ИИ выключен) →
      `specs_status='confirmed'`, Товар уходит из очереди
- [ ] Карточка Товара на витрине показывает новые характеристики
      (`FR-CARD-005`), вывод экранирован
- [ ] POST без CSRF → 419; `manager` доступа не имеет
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 4 — Генератор черновика описания Товара

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-AI-002`: в форме Товара Администратор вводит краткие данные
(категория, материал, размер, механизм) и нажимает «Сгенерировать
черновик» — черновик приходит JSON'ом, показывается в отдельном поле и
сохраняется в `products.description_draft`; опубликованное
`description` не меняется, пока Администратор не нажмёт «Применить к
описанию» и не сохранит форму. Провайдер недоступен → сообщение,
создание и сохранение Товара не блокируются.

**Что нужно создать/изменить:**

- `database/install.php` — изменить: `products.description_draft TEXT
NULL` (идемпотентно); `.docs/database.md`
- `src/Core/AiDescription.php` — создать, чистые:
  `validateDescriptionBrief(array): array` (в промпт попадает только
  то, что ввёл Администратор — правило 3),
  `buildDescriptionPrompt(array): array`,
  `normalizeDraft(string): string` (обрезка длины, снятие разметки,
  `trim`)
- `src/Controllers/AdminProductController.php` — изменить: метод
  `generateDescription(string $id)` — `requireRole(['admin'])`,
  `requireCsrf()`, JSON-ответ `{draft|unavailable|error}`,
  `tooManyAttempts('ai_description', …)`
- `src/Models/Product.php` — изменить: `saveDescriptionDraft(int
$productId, string $draft): void`
- `src/Views/admin/products/form.php` — изменить: блок «Краткие
  данные», кнопка, поле черновика, «Применить к описанию»
- `public/assets/js/admin.js` — изменить: `fetch` на endpoint,
  состояние «генерируется», вывод сообщения при `unavailable`
- `config/routes.php` — изменить:
  `POST /admin/products/{id}/ai-description`
- `tests/Unit/AiDescriptionTest.php` — создать; `tests/bootstrap.php` —
  подключить

**Definition of Done:**

- [ ] Краткие данные → черновик в отдельном поле;
      `products.description` в БД не изменился (`SELECT` до и после)
- [ ] «Применить к описанию» + сохранение формы → описание Товара
      обновилось, на витрине видно
- [ ] Три генерации подряд: в черновике нет характеристик, которых не
      было во входных данных (ручная проверка, правило 3)
- [ ] Провайдер выключен → сообщение под кнопкой, форма Товара
      сохраняется как обычно, описание вводится руками
- [ ] `manager` кнопки не видит; прямой POST от `manager` → отказ,
      черновик не создан
- [ ] POST без CSRF → 419; повторные нажатия → 429 после лимита
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 5 — Консультант в чате: промпт из `content_pages`, endpoint, лог 3 месяца

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-AI-003` (серверная часть): `POST /ai/consultant` принимает текст
вопроса, собирает системный промпт из тел страниц `delivery-payment` и
`return-warranty` плюс реквизитов из `settings`, вызывает провайдера
класса «пользовательский ввод» и возвращает JSON с ответом, либо с
признаком «вне тем» (телефон + WhatsApp), либо «недоступно». Каждое
сообщение пишется в `ai_chat_logs`, записи старше 3 месяцев удаляются.
Виджета ещё нет — проверяется `curl`'ом.

**Что нужно создать/изменить:**

- `database/install.php` — изменить: таблица `ai_chat_logs`
  (`conversation_id CHAR(32)`, `assistant ENUM('consultant','picker')`,
  `role ENUM('user','assistant')`, `message TEXT`, `created_at`,
  `INDEX(conversation_id)`, `INDEX(created_at)`); `.docs/database.md`;
  `.docs/planning-log.md` — ADR-050 (правка `ADR-019`: правила берутся
  из `content_pages`, retrieval по-прежнему нет)
- `src/Core/AiChat.php` — создать, чистые:
  `buildConsultantPrompt(array $pages, array $contacts): string` (круг
  тем, запреты правил 2–5 `FR-AI-003`, формат ответа),
  `normalizeChatQuestion(string): string` (`AI_MAX_QUESTION_LENGTH`),
  `trimChatHistory(array): array` (`AI_CHAT_HISTORY_LIMIT`),
  `chatFallbackPayload(array $contacts): array`
- `src/Models/AiChatLog.php` — создать: `logAiChatMessage(...)`,
  `deleteOldAiChatLogs(int $days): int` + вероятностный вызов чистки
- `src/Controllers/AiChatController.php` — создать: `consultant()` —
  JSON, `requireCsrf()`, `tooManyAttempts('ai_chat', …)`, история
  диалога в `$_SESSION` (не в кабинете — `FR-AI-003` правило 7,
  `FR-AI-004` правило 4), `conversation_id` на сессию
- `config/routes.php` — изменить: `POST /ai/consultant`
- `config/config.php` — изменить: константы чата и чистки лога
- `tests/Unit/AiChatTest.php` — создать; `tests/bootstrap.php` —
  подключить

**Definition of Done:**

- [ ] `curl` с вопросом про сроки доставки → ответ по тексту страницы
      `delivery-payment`, без даты конкретного Заказа
- [ ] Вопрос про гарантийный ремонт купленного дивана и вопрос про цвет
      конкретной модели → предложение позвонить Менеджеру с телефоном и
      ссылкой WhatsApp из `settings` (критерии приёмки `FR-AI-003`)
- [ ] Правка текста страницы в `/admin/content` меняет ответ
      консультанта без правки кода (ручная проверка — смысл ADR-050)
- [ ] В теле запроса к провайдеру — только текст вопроса и системный
      промпт: ни имени, ни телефона, ни данных Заказа (проверено
      временным логированием payload, лог удалён после проверки)
- [ ] Вопрос длиннее лимита обрезается; превышение частоты → 429;
      POST без CSRF → 419
- [ ] Строки пишутся в `ai_chat_logs`; строка с `created_at` старше 90
      дней (вставлена вручную) удаляется чисткой, свежие остаются
- [ ] Класс недоступен → JSON `{"unavailable": true}` с контактами,
      код 200, в логе один `WARNING` без мусора
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 6 — Виджет чата на витрине и предупреждение о личных данных

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-AI-003` (UI) и `AC-06`: на всех страницах витрины — кнопка чата,
открывающая панель с историей текущего диалога, полем ввода,
предупреждением «не указывайте личные данные» (решение `Q-026`) и
кнопками «Позвонить»/WhatsApp. Виджет ничему не мешает: при
недоступности провайдера показывает сообщение и контакты, а оформление
Заказа проходит без ошибок.

**Что нужно создать/изменить:**

- `src/Views/components/ai-chat.php` — создать: разметка виджета в
  стиле темы, семантические теги, `aria-*` на кнопке открытия и области
  сообщений, предупреждение о личных данных
- `src/Views/layout/footer.php` — изменить: подключение компонента
  (только витрина, в Панель не попадает)
- `public/assets/css/app.css` — изменить: BEM-классы `ai-chat__*`,
  mobile-first, CSS-переменные вместо magic numbers
- `public/assets/js/app.js` — изменить: открытие/закрытие, отправка
  `fetch` с CSRF-токеном, рендер сообщений с экранированием, состояние
  «печатает», обработка `unavailable`/429 — внутри существующего IIFE,
  без новых глобальных переменных
- `src/Views/layout/header.php` — изменить (если нужно): мета-тег с
  CSRF-токеном для JS-запросов

**Definition of Done:**

- [ ] Виджет есть на Главной, в Каталоге, на карточке, в корзине и на
      `/checkout`; в Панели управления его нет
- [ ] Диалог из 3 сообщений работает; ответ модели выводится как текст
      (временно подменённый ответ `<script>alert(1)</script>`
      отображается текстом, не исполняется)
- [ ] Замер `NFR-AI-*`: 10 последовательных вопросов, каждый ответ
      ≤ 10 секунд — результат записан в `dev-log.md`
- [ ] Провайдер выключен → сообщение о недоступности + телефон и
      WhatsApp; **оформление Заказа от корзины до «Спасибо» проходит
      без ошибок** (`AC-06`)
- [ ] 320px: виджет не перекрывает кнопки «В корзину»/«Оформить», не
      даёт горизонтального скролла
- [ ] В консоли браузера нет ошибок; в `app.js` не появилось
      собственных глобальных переменных
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 7 — Подбор Товара диалогом

**Статус:** ⏳ Ожидает

**Цель таска:**
`FR-AI-004`: на Главной и в Каталоге — блок «Подобрать диалогом».
Покупатель описывает желаемое свободным текстом, сервер отдаёт модели
снимок каталога **только из активных Товаров с
`specs_status='confirmed'`**, разбирает ответ как список slug'ов,
проверяет каждый по БД и рендерит 2–3 реальные карточки. Ничего не
найдено → пустое состояние со ссылкой на каталог и на Менеджера.

**Что нужно создать/изменить:**

- `src/Core/AiPicker.php` — создать, чистые:
  `buildPickerPrompt(array $catalog, string $query): array`,
  `extractPickedSlugs(?array $decoded, int $limit): array` — только
  slug'и, лимит `AI_PICKER_RESULTS`, дубли схлопываются
- `src/Models/Product.php` — изменить:
  `getConfirmedProductsForAi(int $limit): array` (id, slug, name,
  категории, материалы/механизмы Вариантов, цвета фото, цена с учётом
  скидки через `discountedPriceSql()`, характеристики),
  `getProductsBySlugs(array $slugs): array` (только активные и
  подтверждённые)
- `src/Controllers/AiChatController.php` — изменить: `picker()` — JSON
  с готовыми данными карточек, те же CSRF/лимиты/лог, что у
  консультанта
- `src/Views/components/ai-picker.php` — создать: блок ввода и
  контейнер результатов (карточки рисуются тем же
  `components/product-card.php`, данные приходят из БД)
- `src/Views/home.php`, `src/Views/catalog/index.php` — изменить:
  подключение блока; при недоступном классе блока нет в HTML вовсе (не
  «скрыт CSS» — приём из `ADR-046`)
- `public/assets/js/app.js` — изменить: отправка запроса, рендер
  карточек, пустое состояние
- `config/routes.php` — изменить: `POST /ai/picker`
- `tests/Unit/AiPickerTest.php` — создать; `tests/bootstrap.php` —
  подключить

**Definition of Done:**

- [ ] «Диван в бежевых тонах, не больше 200 см» → 2–3 реальные карточки
      со ссылками на `/product/{slug}`, цены совпадают с каталогом
      (включая скидочные)
- [ ] Выдуманный или несуществующий slug в ответе модели отбрасывается
      — юнит-тест `extractPickedSlugs()` плюс проверка с временно
      подменённым ответом; Покупатель видит пустое состояние, а не
      битую ссылку
- [ ] Товар со `specs_status='pending'` не предлагается: переключение
      статуса одного Товара меняет выдачу (проверено на реальной БД)
- [ ] Нет совпадений → пустое состояние с предложением каталога и связи
      с Менеджером, ни одного Товара «на всякий случай»
- [ ] Провайдер выключен → блока нет в HTML, каталог с фильтрами и
      поиск работают без изменений (`FR-CAT-*`)
- [ ] Неактивный Товар (`is_active=0`) не попадает ни в снимок
      каталога, ни в выдачу
- [ ] POST без CSRF → 419; лимит частоты работает; 320px корректно
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`

---

## Таск 8 — Расход, месячный лимит, ручное отключение + приёмка `AC-06`/`NFR-AI-*`

**Статус:** ⏳ Ожидает

**Цель таска:**
`BR-AI-001` правило 5 и `NFR-AI-*`: `/admin/ai` (только `admin`)
показывает расход за текущий месяц из `ai_requests` — всего, по классам
и по помощникам, — месячный лимит и настройки (лимит, курс USD, цена за
1000 токенов YandexGPT, тумблеры включения каждого из четырёх
помощников). При превышении лимита Владелец получает предупреждение
(баннер в Панели + `logWarning()` + письмо один раз за месяц), а
помощники **продолжают работать** до ручного отключения (`Q-027`).
Финальная сквозная приёмка фазы и закрытие `Q-DEV-005`.

**Что нужно создать/изменить:**

- `src/Core/Ai.php` — изменить: `AI_SETTING_KEYS`
  (`ai_monthly_limit_rub`, `ai_usd_rate`, `ai_yandex_price_per_1k`,
  `ai_specs_enabled`, `ai_description_enabled`, `ai_consultant_enabled`,
  `ai_picker_enabled`), `validateAiSettingsInput(array): array`,
  `aiAssistantEnabled(string): bool` (тумблер гасит помощника так же,
  как отсутствие ключа), `isAiLimitExceeded(string $spend,
string $limit): bool`
- `src/Models/Setting.php` — изменить: `updateSettings(array $values,
array $allowed = SETTING_KEYS)` — форма «Настройки» вызывает как
  раньше, `/admin/ai` передаёт `AI_SETTING_KEYS`; чужие ключи ни одна
  из форм не трогает
- `src/Models/AiUsage.php` — изменить: разбивка расхода по классам и
  помощникам за месяц, число ошибок
- `src/Services/Ai/ai.php` — изменить: после записи расхода — проверка
  лимита, `logWarning()` и `sendMail()` Владельцу не чаще раза в месяц
  (отметка в `settings`, ключ `ai_limit_notified_month`)
- `src/Controllers/AdminAiController.php` — создать: `index()`,
  `update()` — `requireRole(['admin'])`, `requireCsrf()`
- `src/Views/admin/ai/index.php` — создать: расход/лимит/ошибки, форма
  настроек, тумблеры помощников; `src/Views/emails/` — шаблон письма о
  лимите
- `src/Views/layout/admin-header.php` — изменить: баннер превышения
  лимита
- `config/routes.php` — изменить: `GET /admin/ai`, `POST /admin/ai`
- `tests/Unit/AiTest.php` — изменить
- Документация — изменить: `.docs/phases/_status.md` (Фаза 9 →
  ✅ Завершена), `.docs/tz-coverage.md` (`FR-AI-001…004`, `BR-AI-001` →
  Реализовано; `Q-DEV-005` → закрыт), `.docs/modules/ai.md` (правка
  `ADR-019` — тексты из `content_pages`), `.docs/dev-log.md`,
  `.docs/database.md` (сверка с `database/install.php`)

**Definition of Done:**

- [ ] Расход за месяц на `/admin/ai` совпадает с `SUM(cost_rub)` из
      `ai_requests` за тот же период (сверено `SELECT`), разбивка по
      классам и помощникам сходится в сумме
- [ ] Имитация исчерпания (лимит снижен ниже текущего расхода) →
      баннер в Панели, `WARNING` в `app.log`, письмо Владельцу; **все
      четыре помощника продолжают работать**; второе превышение в том
      же месяце письмо не дублирует (`Q-027`)
- [ ] Тумблер «выключить» у каждого помощника: выключенный отвечает
      «недоступно» (чат/подбор) или прячет кнопку (Панель), остальные
      три работают
- [ ] Сквозная приёмка `AC-06` при полностью выключенном ИИ: Главная,
      Каталог, карточка, поиск, корзина, `/checkout` до «Спасибо»,
      личный кабинет, Панель — без ошибок; `app.log` без `ERROR`
- [ ] `NFR-AI-*` зафиксировано в `dev-log.md`: замер 10 ответов
      консультанта, время разбора пакета Товаров, проверка чистки лога
      переписки
- [ ] Секретов в БД нет — ключи провайдеров остались в `.env`
      (`Q-032`, `CLAUDE.md`)
- [ ] `manager` раздела «ИИ» не видит и по прямому URL не открывает
- [ ] `composer test` зелёный
- [ ] Проверить `.docs/dod-global.md`
