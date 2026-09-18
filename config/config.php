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

// Оформление заказа (`.docs/phases/phase-2.md`, Таск 3) — кнопка
// «Позвонить/WhatsApp» (`FR-CHK-005`). TODO: заменить на реальный номер
// магазина перед продакшеном.
define('SHOP_PHONE', '+7 900 000-00-00');
define('SHOP_PHONE_TEL', '+79000000000');
define('SHOP_WHATSAPP_URL', 'https://wa.me/79000000000');

// Панель управления — Заказы (`.docs/phases/phase-4.md`, Таск 2)
define('ADMIN_ORDERS_PER_PAGE', 20);

require_once ROOT_PATH . '/src/Core/Logger.php';
require_once ROOT_PATH . '/src/Core/Database.php';
