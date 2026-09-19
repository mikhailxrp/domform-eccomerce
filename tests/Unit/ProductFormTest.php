<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProductFormTest extends TestCase
{
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name'                => 'Диван «Милан»',
            'slug'                => '',
            'description'         => '',
            'is_featured'         => false,
            'is_active'           => true,
            'category_ids'        => [1],
            'primary_category_id' => 1,
            'specs'               => [],
            'variants'            => [
                [
                    'id' => 0, 'sku' => 'SOFA-1', 'material' => 'Рогожка', 'mechanism_type' => '',
                    'price' => '35000', 'production_time' => '2-3 недели',
                    'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => true,
                ],
            ],
        ], $overrides);
    }

    // ─── validateProductInput() — Товар ─────────────────────────────────

    public function testEmptyNameIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput(['name' => '']));

        $this->assertTrue($errors['name']);
    }

    public function testNoCategoriesIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput(['category_ids' => [], 'primary_category_id' => 0]));

        $this->assertTrue($errors['category_ids']);
    }

    public function testPrimaryNotAmongSelectedIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput(['category_ids' => [1, 2], 'primary_category_id' => 3]));

        $this->assertTrue($errors['primary_category_id']);
    }

    public function testPrimaryAmongTwoSelectedIsValid(): void
    {
        $errors = validateProductInput($this->validInput(['category_ids' => [1, 2], 'primary_category_id' => 2]));

        $this->assertFalse($errors['primary_category_id']);
    }

    public function testZeroPrimaryIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput(['primary_category_id' => 0]));

        $this->assertTrue($errors['primary_category_id']);
    }

    // ─── публикация требует активный Вариант с ценой ────────────────────

    public function testPublishWithoutVariantsIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput(['is_active' => true, 'variants' => []]));

        $this->assertTrue($errors['publish_needs_variant']);
    }

    public function testPublishWithOnlyInactiveVariantIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput([
            'is_active' => true,
            'variants'  => [
                ['id' => 0, 'sku' => 'A', 'material' => 'X', 'mechanism_type' => '', 'price' => '1000',
                 'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => false],
            ],
        ]));

        $this->assertTrue($errors['publish_needs_variant']);
    }

    public function testPublishWithActiveVariantAndPriceIsValid(): void
    {
        $errors = validateProductInput($this->validInput(['is_active' => true]));

        $this->assertFalse($errors['publish_needs_variant']);
    }

    public function testUnpublishedWithoutVariantsIsValid(): void
    {
        $errors = validateProductInput($this->validInput(['is_active' => false, 'variants' => []]));

        $this->assertFalse($errors['publish_needs_variant']);
    }

    // ─── Варианты — цена/скидка/обязательные поля ───────────────────────

    public function testMalformedPriceIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput([
            'variants' => [
                ['id' => 0, 'sku' => 'A', 'material' => 'X', 'mechanism_type' => '', 'price' => '12.345',
                 'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => true],
            ],
        ]));

        $this->assertTrue($errors['variants'][0]['price']);
    }

    public function testZeroPriceIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput([
            'variants' => [
                ['id' => 0, 'sku' => 'A', 'material' => 'X', 'mechanism_type' => '', 'price' => '0',
                 'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => true],
            ],
        ]));

        $this->assertTrue($errors['variants'][0]['price']);
    }

    public function testDiscountOver99_99IsInvalid(): void
    {
        $errors = validateProductInput($this->validInput([
            'variants' => [
                ['id' => 0, 'sku' => 'A', 'material' => 'X', 'mechanism_type' => '', 'price' => '1000',
                 'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '150', 'is_active' => true],
            ],
        ]));

        $this->assertTrue($errors['variants'][0]['discount_percent']);
    }

    public function testDiscount15IsValid(): void
    {
        $errors = validateProductInput($this->validInput([
            'variants' => [
                ['id' => 0, 'sku' => 'A', 'material' => 'X', 'mechanism_type' => '', 'price' => '1000',
                 'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '15', 'is_active' => true],
            ],
        ]));

        $this->assertFalse($errors['variants'][0]['discount_percent']);
    }

    public function testEmptyDiscountIsValid(): void
    {
        $errors = validateProductInput($this->validInput());

        $this->assertFalse($errors['variants'][0]['discount_percent']);
    }

    public function testEmptySkuMaterialProductionTimeAreInvalid(): void
    {
        $errors = validateProductInput($this->validInput([
            'variants' => [
                ['id' => 0, 'sku' => '', 'material' => '', 'mechanism_type' => '', 'price' => '1000',
                 'production_time' => '', 'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => true],
            ],
        ]));

        $this->assertTrue($errors['variants'][0]['sku']);
        $this->assertTrue($errors['variants'][0]['material']);
        $this->assertTrue($errors['variants'][0]['production_time']);
    }

    public function testDuplicateSkuWithinFormIsInvalid(): void
    {
        $variant = ['id' => 0, 'sku' => 'SAME', 'material' => 'X', 'mechanism_type' => '', 'price' => '1000',
            'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => true];

        $errors = validateProductInput($this->validInput(['variants' => [$variant, $variant]]));

        $this->assertTrue($errors['variants'][0]['sku_duplicate']);
        $this->assertTrue($errors['variants'][1]['sku_duplicate']);
    }

    public function testDuplicateSkuCheckIsCaseInsensitive(): void
    {
        $variantA = ['id' => 0, 'sku' => 'same', 'material' => 'X', 'mechanism_type' => '', 'price' => '1000',
            'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => true];
        $variantB = $variantA;
        $variantB['sku'] = 'SAME';

        $errors = validateProductInput($this->validInput(['variants' => [$variantA, $variantB]]));

        $this->assertTrue($errors['variants'][0]['sku_duplicate']);
        $this->assertTrue($errors['variants'][1]['sku_duplicate']);
    }

    public function testUniqueSkusAreValid(): void
    {
        $errors = validateProductInput($this->validInput([
            'variants' => [
                ['id' => 0, 'sku' => 'A', 'material' => 'X', 'mechanism_type' => '', 'price' => '1000',
                 'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => true],
                ['id' => 0, 'sku' => 'B', 'material' => 'X', 'mechanism_type' => '', 'price' => '1000',
                 'production_time' => '1 неделя', 'is_showroom_sample' => false, 'discount_percent' => '', 'is_active' => true],
            ],
        ]));

        $this->assertFalse($errors['variants'][0]['sku_duplicate']);
        $this->assertFalse($errors['variants'][1]['sku_duplicate']);
    }

    // ─── Характеристики — пара «название — значение» ────────────────────

    public function testSpecWithOnlyNameIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput(['specs' => [['name' => 'Ширина', 'value' => '']]]));

        $this->assertTrue($errors['specs'][0]['value']);
        $this->assertFalse($errors['specs'][0]['name']);
    }

    public function testSpecWithOnlyValueIsInvalid(): void
    {
        $errors = validateProductInput($this->validInput(['specs' => [['name' => '', 'value' => '80 см']]]));

        $this->assertTrue($errors['specs'][0]['name']);
    }

    public function testCompleteSpecIsValid(): void
    {
        $errors = validateProductInput($this->validInput(['specs' => [['name' => 'Ширина', 'value' => '80 см']]]));

        $this->assertFalse($errors['specs'][0]['name']);
        $this->assertFalse($errors['specs'][0]['value']);
    }

    public function testAllValidReturnsNoErrors(): void
    {
        $errors = validateProductInput($this->validInput());

        $this->assertFalse(productFormHasErrors($errors));
    }

    // ─── productFormHasErrors() — обход вложенных массивов ──────────────

    public function testHasErrorsDetectsNestedTrue(): void
    {
        $this->assertTrue(productFormHasErrors(['name' => false, 'variants' => [0 => ['sku' => true]]]));
    }

    public function testHasErrorsFalseWhenAllNestedFalse(): void
    {
        $this->assertFalse(productFormHasErrors(['name' => false, 'variants' => [0 => ['sku' => false]]]));
    }

    // ─── normalizeProductInput() / хелперы ───────────────────────────────

    public function testNormalizeGeneratesSlugFromNameWhenSlugEmpty(): void
    {
        $normalized = normalizeProductInput(['name' => 'Диван Милан', 'slug' => '']);

        $this->assertSame('divan-milan', $normalized['slug']);
    }

    public function testNormalizeUsesProvidedSlugOverName(): void
    {
        $normalized = normalizeProductInput(['name' => 'Диван Милан', 'slug' => 'custom-slug']);

        $this->assertSame('custom-slug', $normalized['slug']);
    }

    public function testNormalizeCategoryIdsDropsInvalidAndDuplicates(): void
    {
        $ids = normalizeProductCategoryIds(['1', '2', '1', 'abc', '0', -3]);

        $this->assertSame([1, 2], $ids);
    }

    public function testNormalizeSpecsDropsBlankRows(): void
    {
        $specs = normalizeProductSpecs([
            ['name' => '', 'value' => ''],
            ['name' => 'Ширина', 'value' => '80 см'],
        ]);

        $this->assertCount(1, $specs);
        $this->assertSame('Ширина', $specs[0]['name']);
    }

    public function testNormalizeVariantsDropsBlankTemplateRow(): void
    {
        $variants = normalizeProductVariants([
            ['id' => '0', 'sku' => '', 'material' => '', 'mechanism_type' => '', 'price' => '',
             'production_time' => '', 'is_showroom_sample' => '', 'discount_percent' => '', 'is_active' => ''],
            ['id' => '0', 'sku' => 'A', 'material' => 'X', 'mechanism_type' => '', 'price' => '1000',
             'production_time' => '1 неделя', 'is_showroom_sample' => '1', 'discount_percent' => '', 'is_active' => '1'],
        ]);

        $this->assertCount(1, $variants);
        $this->assertSame('A', $variants[0]['sku']);
        $this->assertTrue($variants[0]['is_showroom_sample']);
        $this->assertTrue($variants[0]['is_active']);
    }

    public function testNormalizeVariantsKeepsRowWithOnlyExistingId(): void
    {
        $variants = normalizeProductVariants([
            ['id' => '5', 'sku' => '', 'material' => '', 'mechanism_type' => '', 'price' => '',
             'production_time' => '', 'is_showroom_sample' => '', 'discount_percent' => '', 'is_active' => ''],
        ]);

        $this->assertCount(1, $variants);
        $this->assertSame(5, $variants[0]['id']);
    }
}
