<?php

declare(strict_types=1);

/**
 * Константы и настройки приложения.
 * Секреты — только через .env, не здесь.
 */

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/src/Core/functions.php';

loadEnv(ROOT_PATH . '/.env');

define('APP_ENV',  env('APP_ENV', 'production'));
define('APP_URL',  env('APP_URL', ''));

// Логгер
define('LOG_DIR',       env('LOG_DIR',       ROOT_PATH . '/storage/logs'));
define('LOG_FILE',      env('LOG_FILE',      'app.log'));
define('APP_LOG_LEVEL', env('APP_LOG_LEVEL', 'error'));

// Каталог (`.docs/phases/phase-1.md`, «Решения фазы»)
define('CATALOG_PER_PAGE', 12);

// Поиск (`.docs/phases/phase-1.md`, Таск 5)
define('SEARCH_SUGGEST_LIMIT', 5);
define('SEARCH_MIN_QUERY_LENGTH', 2);

// Корзина (`.docs/phases/phase-2.md`, Таск 1) — CART_MAX_QUANTITY не
// здесь: он нужен внутри чистой clampCartQuantity() в Core/Cart.php,
// поэтому определён там же (по образцу SEARCH_QUERY_MAX_LENGTH в
// CatalogFilters.php — константы, нужные тестируемым Core-функциям, не
// в config.php, который tests/bootstrap.php не подключает)
define('CART_COOKIE_DAYS', 30);

// Панель управления — Заказы (`.docs/phases/phase-4.md`, Таск 2)
define('ADMIN_ORDERS_PER_PAGE', 20);

// Панель управления — подсказки поиска Варианта при редактировании
// состава Заказа (`.docs/phases/phase-4.md`, Таск 4)
define('ADMIN_VARIANT_SEARCH_LIMIT', 10);

// Панель управления — Клиенты (`.docs/phases/phase-4.md`, Таск 6)
define('ADMIN_CUSTOMERS_PER_PAGE', 20);

// Панель управления — Товары (`.docs/phases/phase-4.md`, Таск 7)
define('ADMIN_PRODUCTS_PER_PAGE', 20);

// Панель управления — Возвраты (`.docs/phases/phase-5.md`, Таск 6)
define('ADMIN_RETURNS_PER_PAGE', 20);

// Панель управления — Отзывы (`.docs/phases/phase-6.md`, Таск 5)
define('ADMIN_REVIEWS_PER_PAGE', 20);

// Панель управления — Заявки на звонок (`.docs/phases/phase-8.md`, Таск 5)
define('ADMIN_CALLBACKS_PER_PAGE', 20);

// Главная — размер блоков Товаров/отзывов (`.docs/phases/phase-6.md`, Таск 6)
define('HOME_BLOCK_LIMIT', 8);

// Личный кабинет — история заказов (`.docs/phases/phase-7.md`, Таск 2).
// Меньше, чем ADMIN_ORDERS_PER_PAGE — у одного Покупателя заказов
// на порядки меньше, чем во всей Панели управления.
define('ACCOUNT_ORDERS_PER_PAGE', 10);

// Личный кабинет — книга адресов (`.docs/phases/phase-7.md`, Таск 4).
// Санитарный предел, ТЗ не задаёт число — у реального Покупателя вряд
// ли больше пары-тройки адресов (дом/дача/работа).
define('ACCOUNT_ADDRESSES_MAX', 10);

// Панель управления — фото Вариантов (`.docs/phases/phase-4.md`, Таск 9)
// Только путь — сам лимит размера/MIME в Core/Upload.php (см. его
// комментарий, tests/bootstrap.php не подключает config.php).
define('UPLOAD_PRODUCTS_DIR', ROOT_PATH . '/public/uploads/products');

// Галерея «О компании» (`ADR-046`) — загруженные фото интерьера/цеха,
// та же схема каталогов, что у `UPLOAD_PRODUCTS_DIR`. Директория общая
// с будущим редактированием фото статических страниц (Таск 6 Фазы 8,
// `phase-8.md`) — то же имя константы, чтобы Таск 6 не переопределял
// её заново.
define('UPLOAD_CONTENT_DIR', ROOT_PATH . '/public/uploads/content');
define('ABOUT_GALLERY_MAX', 4);

// Фото к отзыву о магазине (`ADR-046`) — необязательное поле в форме
// «Добавить отзыв о магазине» Панели управления.
define('UPLOAD_REVIEWS_DIR', ROOT_PATH . '/public/uploads/reviews');

// ИИ-помощники (`.docs/phases/phase-9.md`, Таск 1) — таймаут одного
// HTTP-вызова к провайдеру. Ответ консультанта нужен Покупателю за
// ≤10 секунд (`NFR-AI-*`) — 15 секунд оставляют запас на сеть, не
// превращая зависший провайдер в долгое ожидание для пользователя.
define('AI_TIMEOUT_SECONDS', 15);

// Панель управления — очередь разбора характеристик (`.docs/phases/phase-9.md`,
// Таск 2). `AI_SPECS_BATCH_MAX` — небольшой пакет: несколько
// последовательных блокирующих вызовов провайдера в одном POST-запросе
// рискуют упереться в `max_execution_time` shared-хостинга при большем
// числе Товаров. `AI_SPECS_BATCH_OVERHEAD_SECONDS` — запас сверх
// `AI_TIMEOUT_SECONDS` на один Товар пакета (запросы к БД —
// `getKnownSpecValues()`, `replaceSpecSuggestions()` — и сборка
// промпта); был 5 секунд, не хватило на живом прогоне (Таск 3,
// `dev-log.md` 22.09.2026) — реальный вызов провайдера подошёл близко
// к `AI_TIMEOUT_SECONDS`, а БД проекта на shared-хостинге (Beget) не
// локальная, каждый запрос добавляет сетевую задержку.
define('ADMIN_AI_SPECS_PER_PAGE', 20);
define('AI_SPECS_BATCH_MAX', 5);
define('AI_SPECS_BATCH_OVERHEAD_SECONDS', 20);

// Консультант в чате (`.docs/phases/phase-9.md`, Таск 5) —
// `AI_MAX_QUESTION_LENGTH`/`AI_CHAT_HISTORY_LIMIT` живут в
// `src/Core/AiChat.php`, не здесь: их использует чистая логика
// (`normalizeChatQuestion()`/`trimChatHistory()`), которую подключает
// `tests/bootstrap.php`, а он не подключает `config.php` (по образцу
// констант `AiSpecs.php`/`AiDescription.php`). `AI_CHAT_LOG_RETENTION_DAYS`/
// `AI_CHAT_LOG_GC_DIVISOR` нужны только `Models/AiChatLog.php` (работа
// с БД, юнит-тестами не покрывается) — хранение лога переписки 3
// месяца (`NFR-AI-*`, `Q-028`) и вероятностная чистка при записи, как
// GC сессий.
define('AI_CHAT_LOG_RETENTION_DAYS', 90);
define('AI_CHAT_LOG_GC_DIVISOR', 100);

// Подбор товара внутри Консультанта (объединено с исходным `FR-AI-004`
// — `planning-log.md`) — сколько подтверждённых Товаров попадает в
// снимок каталога для промпта. Используется только в
// `AiChatController` (Model-запрос), поэтому здесь, а не в
// `Core/AiChat.php`. 150 — с запасом на весь текущий каталог.
define('AI_CATALOG_SNAPSHOT_LIMIT', 150);

// Демо-лимит вопросов на один диалог Консультанта (не часть ТЗ —
// ограничение показа для демо/портфолио-стенда, запрошено отдельно).
// `AI_CHAT_LIMIT_ENABLED` — единственное, что нужно `AiChatController`
// из `.env`; сам предел (`AI_CHAT_DEMO_LIMIT`) — в `src/Core/AiChat.php`,
// рядом с проверяющей его чистой функцией `chatLimitReached()`.
define('AI_CHAT_LIMIT_ENABLED', env('LIMIT_REQUESTS', 'false') === 'true');

require_once ROOT_PATH . '/src/Core/Logger.php';
require_once ROOT_PATH . '/src/Core/Database.php';
