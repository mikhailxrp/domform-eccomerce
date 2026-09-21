<?php

declare(strict_types=1);

/**
 * Установка БД — создание таблиц.
 * Запускать один раз из консоли: php database/install.php
 *
 * Схема соответствует .docs/database.md — при добавлении своей таблицы
 * сначала опиши её там, потом продублируй сюда в порядке зависимостей
 * (родитель раньше потомка).
 */

require_once dirname(__DIR__) . '/config/config.php';

$pdo = getPdo();

// ─── Базовые таблицы (.docs/database.md) ──────────────────────────────────

$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100) NOT NULL,
        email         VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        phone         VARCHAR(20) NULL,
        role          ENUM('customer', 'manager', 'admin') NOT NULL DEFAULT 'customer',
        is_blocked    TINYINT(1) NOT NULL DEFAULT 0,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_users_phone (phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS remember_tokens (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        selector   CHAR(24) NOT NULL UNIQUE,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_remember_tokens_user (user_id),
        CONSTRAINT fk_remember_tokens_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at    DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_password_resets_user (user_id),
        CONSTRAINT fk_password_resets_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS categories (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        parent_id   INT NULL,
        name        VARCHAR(150) NOT NULL,
        slug        VARCHAR(160) NOT NULL UNIQUE,
        description TEXT NULL,
        sort_order  INT NOT NULL DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_categories_parent (parent_id),
        CONSTRAINT fk_categories_parent
            FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Таблица уже могла быть развёрнута до ADR-029 (Фаза 0), когда колонки
// description ещё не было — ADD COLUMN не идемпотентен сам по себе,
// поэтому проверяем через information_schema перед ALTER.
$columnExists = $pdo->prepare('
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column
');
$columnExists->execute(['table' => 'categories', 'column' => 'description']);

if ((int) $columnExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE categories ADD COLUMN description TEXT NULL AFTER slug;');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS products (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(200) NOT NULL,
        slug        VARCHAR(220) NOT NULL UNIQUE,
        description TEXT NULL,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_products_active (is_active),
        KEY idx_products_featured (is_featured),
        FULLTEXT KEY ft_products_name_description (name, description)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_specs (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        name       VARCHAR(100) NOT NULL,
        value      VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        KEY idx_product_specs_product (product_id),
        CONSTRAINT fk_product_specs_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_variants (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        product_id          INT NOT NULL,
        sku                 VARCHAR(64) NOT NULL UNIQUE,
        material            VARCHAR(150) NOT NULL,
        mechanism_type      VARCHAR(150) NULL,
        price               DECIMAL(10, 2) NOT NULL,
        production_time     VARCHAR(100) NOT NULL,
        is_showroom_sample  TINYINT(1) NOT NULL DEFAULT 0,
        discount_percent    DECIMAL(5, 2) NULL,
        is_active           TINYINT(1) NOT NULL DEFAULT 1,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_variants_product (product_id),
        KEY idx_variants_price (price),
        KEY idx_variants_showroom (is_showroom_sample),
        KEY idx_variants_material (material),
        CONSTRAINT fk_variants_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS product_categories (
        product_id  INT NOT NULL,
        category_id INT NOT NULL,
        is_primary  TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (product_id, category_id),
        KEY idx_product_categories_category (category_id, product_id),
        CONSTRAINT fk_product_categories_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
        CONSTRAINT fk_product_categories_category
            FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS variant_images (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        product_variant_id  INT NOT NULL,
        color               VARCHAR(100) NULL,
        is_swatch           TINYINT(1) NOT NULL DEFAULT 0,
        path                VARCHAR(255) NOT NULL,
        sort_order          INT NOT NULL DEFAULT 0,
        is_main             TINYINT(1) NOT NULL DEFAULT 0,
        KEY idx_variant_images_variant (product_variant_id),
        KEY idx_variant_images_color (color),
        CONSTRAINT fk_variant_images_variant
            FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Таблица уже могла быть развёрнута до ADR-031 (Фаза 0/Таск 1 Фазы 1),
// когда индекса по color ещё не было — та же идемпотентная проверка
// через information_schema, что и для categories.description (Таск 1).
$indexExists = $pdo->prepare('
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name
');
$indexExists->execute(['table' => 'variant_images', 'index_name' => 'idx_variant_images_color']);

if ((int) $indexExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE variant_images ADD INDEX idx_variant_images_color (color);');
}

// Та же идемпотентная проверка для `product_variants.material` — индекс
// под префиксный поиск `LIKE 'q%'` (Таск 5, `ADR-032`), таблица могла уже
// существовать без него.
$indexExists->execute(['table' => 'product_variants', 'index_name' => 'idx_variants_material']);

if ((int) $indexExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE product_variants ADD INDEX idx_variants_material (material);');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS reviews (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NULL,
        name       VARCHAR(150) NOT NULL,
        email      VARCHAR(150) NOT NULL,
        rating     TINYINT NOT NULL,
        text       TEXT NOT NULL,
        status     ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_reviews_product_status (product_id, status),
        KEY idx_reviews_status (status),
        CONSTRAINT fk_reviews_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// `photo_path` добавлен позже (`ADR-046`, редизайн `/about`) — та же
// идемпотентная проверка через information_schema, что и для
// `categories.description` (Таск 1 Фазы 1): таблица `reviews` уже не
// пуста на большинстве окружений.
$columnExists->execute(['table' => 'reviews', 'column' => 'photo_path']);

if ((int) $columnExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE reviews ADD COLUMN photo_path VARCHAR(255) NULL AFTER text;');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS cart_items (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        session_id          VARCHAR(64) NULL,
        user_id             INT NULL,
        product_variant_id  INT NOT NULL,
        color               VARCHAR(100) NULL,
        quantity            INT NOT NULL DEFAULT 1,
        price_snapshot      DECIMAL(10, 2) NOT NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_cart_items_session (session_id),
        KEY idx_cart_items_user (user_id),
        CONSTRAINT fk_cart_items_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_cart_items_variant
            FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Таблица уже могла быть развёрнута до ADR-033 (Фаза 0), когда
// price_snapshot ещё не было — та же идемпотентная проверка через
// information_schema, что и для categories.description (Таск 1 Фазы 1).
// Колонка не NULL: на момент этого таска cart_items ещё не используется
// (добавление в корзину — Таск 2), таблица пуста на всех окружениях.
$columnExists->execute(['table' => 'cart_items', 'column' => 'price_snapshot']);

if ((int) $columnExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE cart_items ADD COLUMN price_snapshot DECIMAL(10, 2) NOT NULL AFTER quantity;');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS favorites (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_favorites_user_product (user_id, product_id),
        KEY idx_favorites_user (user_id),
        CONSTRAINT fk_favorites_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_favorites_product
            FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS addresses (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        title      VARCHAR(100) NULL,
        city       VARCHAR(100) NOT NULL,
        street     VARCHAR(150) NOT NULL,
        house      VARCHAR(20) NOT NULL,
        apartment  VARCHAR(20) NULL,
        comment    VARCHAR(255) NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_addresses_user (user_id),
        CONSTRAINT fk_addresses_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS orders (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        user_id             INT NULL,
        guest_name          VARCHAR(150) NULL,
        guest_phone         VARCHAR(20) NULL,
        guest_email         VARCHAR(150) NULL,
        status              ENUM(
                                'new', 'confirmed', 'in_production', 'ready_for_shipment',
                                'shipping', 'delivered', 'cancelled'
                            ) NOT NULL DEFAULT 'new',
        fulfillment_method  ENUM('delivery', 'pickup') NOT NULL,
        delivery_address    TEXT NULL,
        comment             TEXT NOT NULL,
        shipping_cost       DECIMAL(10, 2) NULL,
        payment_method      ENUM('card_online', 'cash', 'bank_transfer') NOT NULL,
        payment_status      ENUM('unpaid', 'prepaid', 'paid_full') NOT NULL DEFAULT 'unpaid',
        prepaid_amount      DECIMAL(10, 2) NULL,
        total               DECIMAL(10, 2) NOT NULL,
        delivered_at        TIMESTAMP NULL,
        cancel_note         TEXT NULL,
        prepayment_refunded TINYINT(1) NOT NULL DEFAULT 0,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_orders_user (user_id),
        KEY idx_orders_status (status),
        KEY idx_orders_created (created_at),
        KEY idx_orders_guest_phone (guest_phone),
        CONSTRAINT fk_orders_user
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Таблица уже могла быть развёрнута до ADR-035 (Таск 3 Фазы 2), когда
// comment ещё не было — та же идемпотентная проверка через
// information_schema, что и для cart_items.price_snapshot (Таск 1 Фазы 2).
// Колонка NOT NULL без DEFAULT: orders ещё не используется (создание
// Заказа — этот же таск), таблица пуста на всех окружениях.
$columnExists->execute(['table' => 'orders', 'column' => 'comment']);

if ((int) $columnExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE orders ADD COLUMN comment TEXT NOT NULL AFTER delivery_address;');
}

// Таблица уже могла быть развёрнута до формы отмены Таска 3 Фазы 4
// (`ADR-038`) — та же идемпотентная проверка. `prepayment_refunded`
// с DEFAULT 0 безопасно добавлять и на непустую таблицу (в отличие от
// `comment` выше, у которого не было DEFAULT).
$columnExists->execute(['table' => 'orders', 'column' => 'cancel_note']);

if ((int) $columnExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE orders ADD COLUMN cancel_note TEXT NULL AFTER delivered_at;');
}

$columnExists->execute(['table' => 'orders', 'column' => 'prepayment_refunded']);

if ((int) $columnExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE orders ADD COLUMN prepayment_refunded TINYINT(1) NOT NULL DEFAULT 0 AFTER cancel_note;');
}

// Индексы под поиск Заказа по телефону в Панели управления (`ADR-037`,
// Таск 2 Фазы 4) — та же идемпотентная проверка, что для
// `idx_variant_images_color`/`idx_variants_material` выше: таблицы уже
// могли быть развёрнуты раньше, без этих индексов.
$indexExists->execute(['table' => 'users', 'index_name' => 'idx_users_phone']);

if ((int) $indexExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE users ADD INDEX idx_users_phone (phone);');
}

$indexExists->execute(['table' => 'orders', 'index_name' => 'idx_orders_guest_phone']);

if ((int) $indexExists->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE orders ADD INDEX idx_orders_guest_phone (guest_phone);');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS order_items (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        order_id            INT NOT NULL,
        product_variant_id  INT NULL,
        product_name        VARCHAR(200) NOT NULL,
        variant_sku         VARCHAR(64) NOT NULL,
        variant_material    VARCHAR(150) NOT NULL,
        variant_mechanism   VARCHAR(150) NULL,
        variant_color       VARCHAR(100) NULL,
        price               DECIMAL(10, 2) NOT NULL,
        quantity            INT NOT NULL,
        KEY idx_order_items_order (order_id),
        KEY idx_order_items_variant (product_variant_id),
        CONSTRAINT fk_order_items_order
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
        CONSTRAINT fk_order_items_variant
            FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// reserves: UNIQUE(active_variant_id) — защита от гонки на уровне БД
// (не более одного 'active'-резерва на Вариант), см. .docs/database.md
// и решение по дефекту #5 code-review (партиционированный unique index
// через виртуальную generated column, а не только проверка в Model).
$pdo->exec("
    CREATE TABLE IF NOT EXISTS reserves (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        order_item_id       INT NOT NULL,
        product_variant_id  INT NOT NULL,
        status              ENUM('active', 'released', 'fulfilled') NOT NULL DEFAULT 'active',
        active_variant_id   INT GENERATED ALWAYS AS (
                                IF(status = 'active', product_variant_id, NULL)
                            ) VIRTUAL,
        agreed_until        DATE NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        released_at         TIMESTAMP NULL,
        UNIQUE KEY uq_reserves_order_item (order_item_id),
        UNIQUE KEY uq_reserves_active_variant (active_variant_id),
        KEY idx_reserves_variant_status (product_variant_id, status),
        CONSTRAINT fk_reserves_order_item
            FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE CASCADE,
        CONSTRAINT fk_reserves_variant
            FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS returns (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        order_id   INT NOT NULL,
        note       TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_returns_order (order_id),
        CONSTRAINT fk_returns_order
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS payment_logs (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        order_id        INT NOT NULL,
        provider        VARCHAR(50) NOT NULL,
        signature_valid TINYINT(1) NOT NULL,
        payload         TEXT NOT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_payment_logs_order (order_id),
        CONSTRAINT fk_payment_logs_order
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS sms_notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        order_id   INT NOT NULL,
        phone      VARCHAR(20) NOT NULL,
        event      VARCHAR(30) NOT NULL,
        message    VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sms_notifications_order (order_id),
        CONSTRAINT fk_sms_notifications_order
            FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS content_pages (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        slug       VARCHAR(60) NOT NULL UNIQUE,
        title      VARCHAR(200) NOT NULL,
        body       TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Стартовые тексты статических страниц (`FR-CNT-001…006`, Таск 1
// Фазы 8). Тело — плоский текст с мини-разметкой (`ADR-044`): пустая
// строка = новый блок, `## ` = подзаголовок, `- ` = пункт списка.
// `INSERT IGNORE` по `UNIQUE(slug)` — повторный запуск не перетирает
// уже отредактированный в Панели управления текст.
$contentPages = [
    [
        'slug'  => 'contacts',
        'title' => 'Контакты',
        'body'  => "Свяжитесь с нами любым удобным способом — по телефону, в WhatsApp или через форму обратного звонка.\n\n"
            . "Менеджер перезвонит в рабочее время, ответит на вопросы по моделям, тканям и срокам изготовления, поможет оформить заказ.",
    ],
    [
        'slug'  => 'about',
        'title' => 'О компании',
        'body'  => "ДомФорм — мебель на заказ для дома в Краснодаре и крае. Мы делаем диваны, кресла, кровати и корпусную мебель под ваш размер, ткань и интерьер.\n\n"
            . "## Как мы работаем\n\n"
            . "- Вы выбираете модель в каталоге или присылаете фото понравившейся мебели\n"
            . "- Менеджер согласовывает ткань, размер, механизм и срок по телефону\n"
            . "- Мы изготавливаем мебель в собственном цехе и доставляем по адресу\n\n"
            . "На всю мебель действует гарантия производителя 18 месяцев.",
    ],
    [
        'slug'  => 'showroom',
        'title' => 'Адрес шоурума',
        'body'  => "Посмотреть образцы тканей и готовую мебель можно в нашем цехе в Краснодаре.\n\n"
            . "Посещение — только по предварительной записи: позвоните или напишите в WhatsApp, и менеджер согласует удобное время.",
    ],
    [
        'slug'  => 'delivery-payment',
        'title' => 'Доставка и оплата',
        'body'  => "## Способы получения\n\n"
            . "- Самовывоз из цеха в Краснодаре — по предварительной записи\n"
            . "- Доставка собственным транспортом по Краснодару и краю\n\n"
            . "Стоимость доставки зависит от адреса и габаритов — менеджер называет её при подтверждении заказа по телефону.\n\n"
            . "## Способы оплаты\n\n"
            . "- Картой на сайте\n"
            . "- Наличными при получении\n"
            . "- Переводом на карту\n\n"
            . "Мебель изготавливается под заказ, поэтому после подтверждения вносится предоплата 30–50 % от стоимости. Точный размер предоплаты согласует менеджер. Остаток оплачивается при получении.",
    ],
    [
        'slug'  => 'return-warranty',
        'title' => 'Возврат и гарантия',
        'body'  => "## Гарантия\n\n"
            . "На всю мебель действует гарантия производителя 18 месяцев с момента доставки или сборки. Гарантия покрывает дефекты каркаса, механизмов и обивки, возникшие не по вине покупателя.\n\n"
            . "## Возврат\n\n"
            . "Мебель изготавливается под заказ по индивидуальным параметрам. Вопросы возврата и обмена решаются по звонку менеджеру — возврат возможен только целиком, автоматического возврата денег через сайт нет.\n\n"
            . "- Позвоните нам или напишите в WhatsApp, назовите номер заказа\n"
            . "- Менеджер уточнит причину и согласует порядок возврата\n"
            . "- Возврат предоплаты выполняется переводом на карту",
    ],
    [
        'slug'  => 'offer',
        'title' => 'Публичная оферта',
        'body'  => "Настоящий документ является публичной офертой (предложением) о заключении договора купли-продажи мебели, изготавливаемой по индивидуальному заказу.\n\n"
            . "## 1. Общие положения\n\n"
            . "Оформление заказа на сайте означает согласие покупателя с условиями настоящей оферты. Продавец — ДомФорм (реквизиты указываются владельцем магазина).\n\n"
            . "## 2. Предмет договора\n\n"
            . "Продавец обязуется изготовить и передать покупателю мебель согласно параметрам, согласованным при подтверждении заказа (модель, материал, размер, механизм), а покупатель — принять и оплатить её.\n\n"
            . "## 3. Цена и порядок оплаты\n\n"
            . "Цена указывается на сайте на момент оформления заказа. После подтверждения вносится предоплата 30–50 %, остаток оплачивается при получении.\n\n"
            . "## 4. Сроки и доставка\n\n"
            . "Срок изготовления указывается для каждой модели и подтверждается менеджером. Доставка выполняется по адресу покупателя либо самовывозом по записи.\n\n"
            . "## 5. Гарантия и возврат\n\n"
            . "Гарантия производителя — 18 месяцев. Порядок возврата описан на странице «Возврат и гарантия».\n\n"
            . "Типовой текст — не является индивидуальной юридической консультацией и утверждается владельцем магазина.",
    ],
    [
        'slug'  => 'privacy-policy',
        'title' => 'Политика конфиденциальности',
        'body'  => "Настоящая политика описывает, какие персональные данные собирает сайт ДомФорм и как они используются, в соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных».\n\n"
            . "## Какие данные мы собираем\n\n"
            . "- Имя, телефон и email — при оформлении заказа, регистрации и запросе обратного звонка\n"
            . "- Адрес доставки — при выборе доставки\n"
            . "- Технические данные (cookie, IP-адрес) — для работы корзины и авторизации\n\n"
            . "## Для чего используем\n\n"
            . "- Оформление, подтверждение и доставка заказа\n"
            . "- Связь с покупателем по вопросам заказа\n"
            . "- Уведомления о статусе заказа\n\n"
            . "## Хранение и передача\n\n"
            . "Данные хранятся на сервере магазина и не передаются третьим лицам, кроме случаев, предусмотренных законом. Покупатель вправе запросить уточнение, блокировку или удаление своих данных, обратившись к продавцу по контактам, указанным на сайте.\n\n"
            . "Типовой текст — утверждается владельцем магазина.",
    ],
];

$insertContentPage = $pdo->prepare('
    INSERT IGNORE INTO content_pages (slug, title, body) VALUES (:slug, :title, :body)
');
foreach ($contentPages as $contentPage) {
    $insertContentPage->execute($contentPage);
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS banners (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        title      VARCHAR(200) NULL,
        link       VARCHAR(255) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active  TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_banners_active_sort (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// `banners` не имеет уникального ключа кроме `id` — в отличие от
// `sales_channels`/`integrations` ниже (`UNIQUE(code)`), `INSERT IGNORE`
// здесь не защитил бы от дублей при повторном запуске. Сидим только
// если таблица пуста (`.docs/phases/phase-6.md`, Таск 6).
if ((int) $pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn() === 0) {
    $pdo->exec("
        INSERT INTO banners (image_path, title, link, sort_order, is_active) VALUES
            ('assets/images/slider/slider-item-1.png', 'Мебель на заказ для вашего дома', '/catalog', 1, 1),
            ('assets/images/slider/slider-item-2.png', 'Стиль и комфорт в каждой детали', '/catalog', 2, 1),
            ('assets/images/slider/slider-item-3.png', 'Мебель, сделанная для вас', '/catalog', 3, 1);
    ");
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS sales_channels (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        code        VARCHAR(30) NOT NULL UNIQUE,
        name        VARCHAR(100) NOT NULL,
        description VARCHAR(255) NULL,
        icon        VARCHAR(50) NULL,
        is_locked   TINYINT(1) NOT NULL DEFAULT 0,
        is_enabled  TINYINT(1) NOT NULL DEFAULT 0,
        sort_order  INT NOT NULL DEFAULT 0,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    INSERT IGNORE INTO sales_channels (code, name, description, icon, is_locked, is_enabled, sort_order) VALUES
        ('website',  'Заявки с сайта', 'Заказы, оформленные покупателем на сайте — основной канал, отключить нельзя.', 'bx bx-globe', 1, 1, 1),
        ('whatsapp', 'WhatsApp', 'Приём и обработка сообщений из WhatsApp Business.', 'bx bxl-whatsapp', 0, 0, 2),
        ('avito',    'Авито', 'Отклики и сообщения по объявлениям на Авито.', 'bx bx-store-alt', 0, 0, 3),
        ('telegram', 'Telegram', 'Сообщения из Telegram-бота или канала магазина.', 'bx bxl-telegram', 0, 0, 4),
        ('max',      'MAX', 'Сообщения из мессенджера MAX.', 'bx bx-chat', 0, 0, 5);
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS integrations (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        code        VARCHAR(30) NOT NULL UNIQUE,
        category    VARCHAR(30) NOT NULL,
        name        VARCHAR(100) NOT NULL,
        description VARCHAR(255) NULL,
        icon        VARCHAR(50) NULL,
        is_enabled  TINYINT(1) NOT NULL DEFAULT 0,
        sort_order  INT NOT NULL DEFAULT 0,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    INSERT IGNORE INTO integrations (code, category, name, description, icon, is_enabled, sort_order) VALUES
        ('bitrix24', 'crm', 'Битрикс24', 'Передача заказов и карточек клиентов в Битрикс24.', 'bx bx-git-branch', 0, 1),
        ('amocrm',   'crm', 'amoCRM', 'Передача заказов и сделок в amoCRM.', 'bx bx-git-branch', 0, 2),
        ('1c',        'accounting', '1С', 'Выгрузка заказов и остатков в 1С:Управление торговлей.', 'bx bx-calculator', 0, 1),
        ('moysklad',  'accounting', 'МойСклад', 'Синхронизация остатков и заказов с МойСклад.', 'bx bx-calculator', 0, 2),
        ('telephony', 'telephony', 'IP-телефония', 'Всплывающая карточка клиента при звонке, запись разговоров.', 'bx bx-phone-call', 0, 1),
        ('sms',       'marketing', 'SMS-рассылки', 'Уведомления клиентам о статусе заказа по SMS.', 'bx bx-message-square-dots', 0, 1),
        ('email',     'marketing', 'Email-рассылки', 'Автоматические письма клиентам и маркетинговые рассылки.', 'bx bx-mail-send', 0, 2);
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        `key`      VARCHAR(60) NOT NULL UNIQUE,
        value      TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Реквизиты магазина (`FR-ADM-007` п. 1 и 4 в минимальном составе,
// `ADR-045`, Таск 2 Фазы 8) — сид значений бывших констант `SHOP_*`.
// `INSERT IGNORE` по `UNIQUE(key)` — повторный запуск не перетирает
// значение, отредактированное в Панели управления.
$settingsSeed = [
    ['key' => 'shop_phone',        'value' => '+7 900 000-00-00'],
    ['key' => 'shop_whatsapp_url', 'value' => 'https://wa.me/79000000000'],
    ['key' => 'shop_email',        'value' => 'info@domform.ru'],
    ['key' => 'workshop_address',  'value' => 'г. Краснодар, ул. Промышленная, 1'],
    ['key' => 'work_hours',        'value' => 'Пн–Сб, 9:00–19:00'],
    ['key' => 'map_embed_url',     'value' => ''],
];

$insertSetting = $pdo->prepare('INSERT IGNORE INTO settings (`key`, value) VALUES (:key, :value)');
foreach ($settingsSeed as $setting) {
    $insertSetting->execute($setting);
}

// Галерея «О компании» (`ADR-046`) — до 4 фото, загружаются Менеджером/
// Администратором в Панели управления; строк нет по умолчанию (секция
// на `/about` отсутствует, пока не загружено хотя бы одно фото).
$pdo->exec("
    CREATE TABLE IF NOT EXISTS about_gallery_images (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        path       VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_about_gallery_images_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Добавляй свои таблицы здесь (после базовых, с учётом их FK):
// $pdo->exec("CREATE TABLE IF NOT EXISTS ...");

echo "✅ Таблицы созданы успешно.\n";
