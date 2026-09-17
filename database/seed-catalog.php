<?php

declare(strict_types=1);

/**
 * Наполняет БД тестовым каталогом для Фазы 1 (листинг, карточка, поиск) —
 * CRUD товаров через Панель управления появится в Фазе 4, до неё каталог
 * заполняется отсюда, на фото из купленной темы (`public/assets/images/`).
 *
 * Запускать из консоли: php database/seed-catalog.php
 * Идемпотентен — категория/товар с уже существующим slug пропускается
 * целиком, вместе со своими вариантами/фото/характеристиками.
 */

require_once dirname(__DIR__) . '/config/config.php';

$pdo = getPdo();

// ─── Категории (6, 2 уровня) ────────────────────────────────────────────────

$categories = [
    [
        'slug' => 'myagkaya-mebel', 'name' => 'Мягкая мебель', 'parent' => null,
        'description' => null, 'sort_order' => 10,
    ],
    [
        'slug' => 'divany', 'name' => 'Диваны', 'parent' => 'myagkaya-mebel',
        'description' => 'Диваны на заказ: раскладные и стационарные модели, любой материал обивки и размер спального места.',
        'sort_order' => 10,
    ],
    [
        'slug' => 'kresla', 'name' => 'Кресла', 'parent' => 'myagkaya-mebel',
        'description' => null, 'sort_order' => 20,
    ],
    [
        'slug' => 'spalnya', 'name' => 'Спальня', 'parent' => null,
        'description' => null, 'sort_order' => 20,
    ],
    [
        'slug' => 'krovati', 'name' => 'Кровати', 'parent' => 'spalnya',
        'description' => 'Кровати из массива, ЛДСП и мягкие модели с изголовьем — под любой размер спального места.',
        'sort_order' => 10,
    ],
    [
        'slug' => 'shkafy', 'name' => 'Шкафы', 'parent' => 'spalnya',
        'description' => null, 'sort_order' => 20,
    ],
];

// ─── Товары (12) ─────────────────────────────────────────────────────────────
// categories: [slug категории => является ли главной для крошек/URL]

$products = [
    [
        'slug' => 'divan-milan', 'name' => 'Диван «Милан»',
        'description' => 'Стационарный диван с широкими подлокотниками и съёмными чехлами.',
        'categories' => ['divany' => true],
        'specs' => [
            ['name' => 'Ширина', 'value' => '220 см'],
            ['name' => 'Глубина', 'value' => '95 см'],
            ['name' => 'Высота', 'value' => '85 см'],
            ['name' => 'Спальное место', 'value' => '140×200 см'],
        ],
        'variants' => [
            [
                'sku' => 'SOFA-MILAN-FABRIC', 'material' => 'Рогожка', 'mechanism_type' => 'Еврокнижка',
                'price' => '35000.00', 'production_time' => '3-4 недели',
                'images' => [
                    ['color' => 'Серый', 'path' => 'assets/images/product/product-01.jpg', 'is_main' => true],
                ],
            ],
            [
                'sku' => 'SOFA-MILAN-ECO', 'material' => 'Экокожа', 'mechanism_type' => 'Еврокнижка',
                'price' => '42000.00', 'production_time' => '4-5 недель',
                'images' => [
                    ['color' => 'Коричневый', 'path' => 'assets/images/product/product-02.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'divan-verona', 'name' => 'Диван «Верона»',
        'description' => 'Раскладной диван «дельфин» с ортопедическим основанием и бельевым ящиком.',
        'categories' => ['divany' => true],
        'specs' => [
            ['name' => 'Ширина', 'value' => '230 см'],
            ['name' => 'Глубина', 'value' => '100 см'],
            ['name' => 'Высота', 'value' => '90 см'],
            ['name' => 'Спальное место', 'value' => '160×200 см'],
        ],
        'variants' => [
            [
                'sku' => 'SOFA-VERONA-FABRIC', 'material' => 'Велюр', 'mechanism_type' => 'Дельфин',
                'price' => '38000.00', 'production_time' => '3-4 недели',
                'images' => [
                    ['color' => 'Изумрудный', 'path' => 'assets/images/product/product-03.jpg', 'is_main' => true],
                ],
            ],
            [
                // Единственный Вариант-образец во всех сидах (`_status.md`, DoD Таска 1).
                'sku' => 'SOFA-VERONA-ECO', 'material' => 'Экокожа', 'mechanism_type' => 'Дельфин',
                'price' => '45000.00', 'production_time' => '4-5 недель', 'is_showroom_sample' => true,
                'images' => [
                    ['color' => 'Серый', 'path' => 'assets/images/product/product-04.jpg', 'is_main' => true],
                    ['color' => 'Бежевый', 'path' => 'assets/images/product/product-05.jpg'],
                    // is_swatch=1 — только образец ткани, реального фото Варианта в этом цвете нет.
                    ['color' => 'Коричневый', 'path' => 'assets/images/product-details/product-details-1.jpg', 'is_swatch' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'divan-florenciya', 'name' => 'Угловой диван «Флоренция»',
        'description' => 'Стационарный угловой диван для просторной гостиной, без спального места.',
        'categories' => ['divany' => true],
        'specs' => [
            ['name' => 'Ширина', 'value' => '280 см'],
            ['name' => 'Глубина', 'value' => '180 см'],
            ['name' => 'Высота', 'value' => '88 см'],
        ],
        'variants' => [
            [
                'sku' => 'SOFA-FLORENCE-ROGOZHKA', 'material' => 'Рогожка', 'mechanism_type' => null,
                'price' => '89000.00', 'production_time' => '5-6 недель',
                'images' => [
                    ['color' => 'Графитовый', 'path' => 'assets/images/product/product-06.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'divan-krovat-oslo', 'name' => 'Диван-кровать «Осло»',
        'description' => 'Компактный диван-кровать на механизме «аккордеон» — для гостиной и спальни одновременно.',
        // Товар в двух категориях сразу — is_primary только у «Диваны» (DoD Таска 1).
        'categories' => ['divany' => true, 'krovati' => false],
        'specs' => [
            ['name' => 'Ширина', 'value' => '200 см'],
            ['name' => 'Спальное место', 'value' => '140×195 см'],
        ],
        'variants' => [
            [
                'sku' => 'SOFA-OSLO-FABRIC', 'material' => 'Рогожка', 'mechanism_type' => 'Аккордеон',
                'price' => '52000.00', 'production_time' => '4-5 недель',
                'images' => [
                    ['color' => 'Синий', 'path' => 'assets/images/product/product-07.jpg', 'is_main' => true],
                ],
            ],
            [
                'sku' => 'SOFA-OSLO-VELUR', 'material' => 'Велюр', 'mechanism_type' => 'Аккордеон',
                'price' => '58000.00', 'production_time' => '5 недель',
                'images' => [
                    ['color' => 'Горчичный', 'path' => 'assets/images/product/product-08.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'kreslo-komfort', 'name' => 'Кресло «Комфорт»',
        'description' => 'Классическое кресло на деревянных ножках.',
        'categories' => ['kresla' => true],
        'specs' => [
            ['name' => 'Ширина', 'value' => '80 см'],
            ['name' => 'Глубина', 'value' => '85 см'],
            ['name' => 'Высота', 'value' => '95 см'],
        ],
        'variants' => [
            [
                // mechanism_type = NULL — механизм неприменим к обычному креслу (DoD Таска 1).
                'sku' => 'CHAIR-KOMFORT-ECO', 'material' => 'Экокожа', 'mechanism_type' => null,
                'price' => '18000.00', 'production_time' => '2-3 недели',
                'images' => [
                    ['color' => 'Чёрный', 'path' => 'assets/images/product/product-09.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'kreslo-kachalka-uyut', 'name' => 'Кресло-качалка «Уют»',
        'description' => 'Кресло-качалка с мягкой обивкой и подлокотниками.',
        'categories' => ['kresla' => true],
        // Без характеристик — проверка, что блок таблицы скрывается, а не рендерится пустым (Таск 4).
        'specs' => [],
        'variants' => [
            [
                'sku' => 'CHAIR-UYUT-FABRIC', 'material' => 'Рогожка', 'mechanism_type' => 'Качалка',
                'price' => '22000.00', 'production_time' => '3 недели',
                'images' => [
                    ['color' => 'Бежевый', 'path' => 'assets/images/product/product-10.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'kreslo-loft', 'name' => 'Кресло «Лофт»',
        'description' => 'Кресло в стиле лофт на металлическом каркасе.',
        'categories' => ['kresla' => true],
        'specs' => [
            ['name' => 'Ширина', 'value' => '75 см'],
            ['name' => 'Глубина', 'value' => '80 см'],
        ],
        'variants' => [
            [
                'sku' => 'CHAIR-LOFT-FABRIC', 'material' => 'Рогожка', 'mechanism_type' => null,
                'price' => '19500.00', 'production_time' => '2-3 недели',
                'images' => [
                    ['color' => 'Горчичный', 'path' => 'assets/images/product/product-11.jpg', 'is_main' => true],
                ],
            ],
            [
                'sku' => 'CHAIR-LOFT-VELVET', 'material' => 'Вельвет', 'mechanism_type' => null,
                'price' => '21000.00', 'production_time' => '3 недели',
                'images' => [
                    ['color' => 'Изумрудный', 'path' => 'assets/images/product/product-12.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'krovat-verona', 'name' => 'Кровать «Верона»',
        'description' => 'Кровать из массива дуба с мягким изголовьем.',
        'categories' => ['krovati' => true],
        'specs' => [
            ['name' => 'Спальное место', 'value' => '160×200 см'],
            ['name' => 'Высота изголовья', 'value' => '120 см'],
        ],
        'variants' => [
            [
                'sku' => 'BED-VERONA-OAK', 'material' => 'Массив дуба', 'mechanism_type' => null,
                'price' => '54000.00', 'production_time' => '5-6 недель',
                'images' => [
                    ['color' => 'Натуральный дуб', 'path' => 'assets/images/product/product-13.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'krovat-podium-provans', 'name' => 'Кровать-подиум «Прованс»',
        'description' => 'Кровать-подиум с подъёмным механизмом и вместительными ящиками для хранения.',
        'categories' => ['krovati' => true],
        'specs' => [
            ['name' => 'Спальное место', 'value' => '160×200 см'],
            ['name' => 'Объём ящиков', 'value' => '350 л'],
        ],
        'variants' => [
            [
                'sku' => 'BED-PROVANCE-LDSP', 'material' => 'ЛДСП', 'mechanism_type' => 'Подъёмный',
                'price' => '61000.00', 'production_time' => '5 недель',
                'images' => [
                    ['color' => 'Белый', 'path' => 'assets/images/product-details/product-details-2.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'krovat-detskaya-malysh', 'name' => 'Кровать детская «Малыш»',
        'description' => 'Компактная детская кровать с бортиком безопасности.',
        'categories' => ['krovati' => true],
        'specs' => [
            ['name' => 'Спальное место', 'value' => '80×190 см'],
        ],
        'variants' => [
            [
                'sku' => 'BED-MALYSH-MDF', 'material' => 'МДФ', 'mechanism_type' => null,
                'price' => '27000.00', 'production_time' => '3-4 недели',
                'images' => [
                    ['color' => 'Белый', 'path' => 'assets/images/product-details/product-details-3.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        'slug' => 'shkaf-kupe-garderob', 'name' => 'Шкаф-купе «Гардероб»',
        'description' => 'Шкаф-купе с раздвижными дверями и системой внутренних полок.',
        'categories' => ['shkafy' => true],
        'specs' => [
            ['name' => 'Ширина', 'value' => '240 см'],
            ['name' => 'Глубина', 'value' => '60 см'],
            ['name' => 'Высота', 'value' => '240 см'],
        ],
        'variants' => [
            [
                'sku' => 'WARDROBE-GARDEROB-WHITE', 'material' => 'ЛДСП белый', 'mechanism_type' => 'Купе',
                'price' => '48000.00', 'production_time' => '4 недели',
                'images' => [
                    ['color' => 'Белый', 'path' => 'assets/images/product-details/product-details-4.jpg', 'is_main' => true],
                ],
            ],
            [
                'sku' => 'WARDROBE-GARDEROB-WENGE', 'material' => 'ЛДСП венге', 'mechanism_type' => 'Купе',
                'price' => '49000.00', 'production_time' => '4 недели',
                'images' => [
                    ['color' => 'Венге', 'path' => 'assets/images/product-details/product-details-5.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
    [
        // Неактивный товар — проверка скрытия из каталога (DoD Таска 1/2).
        'slug' => 'shkaf-klassik', 'name' => 'Шкаф распашной «Классик»', 'is_active' => false,
        'description' => 'Распашной шкаф из ЛДСП с антресолью.',
        'categories' => ['shkafy' => true],
        'specs' => [],
        'variants' => [
            [
                'sku' => 'WARDROBE-KLASSIK-LDSP', 'material' => 'ЛДСП', 'mechanism_type' => 'Распашной',
                'price' => '32000.00', 'production_time' => '3 недели',
                'images' => [
                    ['color' => 'Дуб сонома', 'path' => 'assets/images/product/product-01.jpg', 'is_main' => true],
                ],
            ],
        ],
    ],
];

// ─── Наполнение ──────────────────────────────────────────────────────────────

$categoryIds = [];
foreach ($categories as $category) {
    $parentId = $category['parent'] !== null ? $categoryIds[$category['parent']] : null;
    $categoryIds[$category['slug']] = findOrCreateCategory($pdo, $category, $parentId);
}

$created = 0;
$skipped = 0;

foreach ($products as $product) {
    if (productExists($pdo, $product['slug'])) {
        $skipped++;
        continue;
    }

    createProduct($pdo, $product, $categoryIds);
    $created++;
}

echo "✅ Категории готовы: " . count($categoryIds) . ".\n";
echo "✅ Товары: создано {$created}, пропущено (уже были) {$skipped}.\n";

// ─── Хелперы ───────────────────────────────────────────────────────────────

function findOrCreateCategory(PDO $pdo, array $data, ?int $parentId): int
{
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug');
    $stmt->execute(['slug' => $data['slug']]);
    $existingId = $stmt->fetchColumn();

    if ($existingId !== false) {
        return (int) $existingId;
    }

    $insert = $pdo->prepare(
        'INSERT INTO categories (parent_id, name, slug, description, sort_order)
         VALUES (:parent_id, :name, :slug, :description, :sort_order)'
    );
    $insert->execute([
        'parent_id'   => $parentId,
        'name'        => $data['name'],
        'slug'        => $data['slug'],
        'description' => $data['description'],
        'sort_order'  => $data['sort_order'],
    ]);

    return (int) $pdo->lastInsertId();
}

function productExists(PDO $pdo, string $slug): bool
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = :slug');
    $stmt->execute(['slug' => $slug]);

    return $stmt->fetchColumn() !== false;
}

function createProduct(PDO $pdo, array $product, array $categoryIds): void
{
    $pdo->beginTransaction();

    try {
        $insertProduct = $pdo->prepare(
            'INSERT INTO products (name, slug, description, is_active)
             VALUES (:name, :slug, :description, :is_active)'
        );
        $insertProduct->execute([
            'name'        => $product['name'],
            'slug'        => $product['slug'],
            'description' => $product['description'],
            'is_active'   => ($product['is_active'] ?? true) ? 1 : 0,
        ]);
        $productId = (int) $pdo->lastInsertId();

        $insertProductCategory = $pdo->prepare(
            'INSERT INTO product_categories (product_id, category_id, is_primary)
             VALUES (:product_id, :category_id, :is_primary)'
        );
        foreach ($product['categories'] as $categorySlug => $isPrimary) {
            $insertProductCategory->execute([
                'product_id'  => $productId,
                'category_id' => $categoryIds[$categorySlug],
                'is_primary'  => $isPrimary ? 1 : 0,
            ]);
        }

        $insertSpec = $pdo->prepare(
            'INSERT INTO product_specs (product_id, name, value, sort_order)
             VALUES (:product_id, :name, :value, :sort_order)'
        );
        foreach ($product['specs'] as $sortOrder => $spec) {
            $insertSpec->execute([
                'product_id' => $productId,
                'name'       => $spec['name'],
                'value'      => $spec['value'],
                'sort_order' => $sortOrder,
            ]);
        }

        $insertVariant = $pdo->prepare(
            'INSERT INTO product_variants
                (product_id, sku, material, mechanism_type, price, production_time, is_showroom_sample)
             VALUES (:product_id, :sku, :material, :mechanism_type, :price, :production_time, :is_showroom_sample)'
        );
        $insertImage = $pdo->prepare(
            'INSERT INTO variant_images (product_variant_id, color, is_swatch, path, sort_order, is_main)
             VALUES (:product_variant_id, :color, :is_swatch, :path, :sort_order, :is_main)'
        );

        foreach ($product['variants'] as $variant) {
            $insertVariant->execute([
                'product_id'         => $productId,
                'sku'                => $variant['sku'],
                'material'           => $variant['material'],
                'mechanism_type'     => $variant['mechanism_type'],
                'price'              => $variant['price'],
                'production_time'    => $variant['production_time'],
                'is_showroom_sample' => ($variant['is_showroom_sample'] ?? false) ? 1 : 0,
            ]);
            $variantId = (int) $pdo->lastInsertId();

            foreach ($variant['images'] as $sortOrder => $image) {
                $insertImage->execute([
                    'product_variant_id' => $variantId,
                    'color'              => $image['color'],
                    'is_swatch'          => ($image['is_swatch'] ?? false) ? 1 : 0,
                    'path'               => $image['path'],
                    'sort_order'         => $sortOrder,
                    'is_main'            => ($image['is_main'] ?? false) ? 1 : 0,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
