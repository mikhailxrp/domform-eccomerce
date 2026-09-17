<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CatalogFiltersTest extends TestCase
{
    public function testDefaultsOnEmptyQuery(): void
    {
        $filters = normalizeCatalogFilters([]);

        $this->assertSame('newest', $filters['sort']);
        $this->assertSame([], $filters['category_ids']);
        $this->assertSame([], $filters['colors']);
        $this->assertNull($filters['price_min']);
        $this->assertNull($filters['price_max']);
        $this->assertFalse($filters['in_stock']);
    }

    public function testUnknownSortFallsBackToNewest(): void
    {
        $filters = normalizeCatalogFilters(['sort' => 'popularity']);

        $this->assertSame('newest', $filters['sort']);
    }

    public function testAllowedSortIsKept(): void
    {
        $filters = normalizeCatalogFilters(['sort' => 'price_asc']);

        $this->assertSame('price_asc', $filters['sort']);
    }

    public function testCategoryIdsAreCastToIntAndSorted(): void
    {
        $filters = normalizeCatalogFilters(['category' => ['5', '2', 'abc', '5', '-1', '0']]);

        $this->assertSame([2, 5], $filters['category_ids']);
    }

    public function testCategorySingleScalarIsWrappedIntoArray(): void
    {
        $filters = normalizeCatalogFilters(['category' => '3']);

        $this->assertSame([3], $filters['category_ids']);
    }

    public function testColorsAreTrimmedDeduplicatedAndSorted(): void
    {
        $filters = normalizeCatalogFilters(['color' => [' Серый ', 'Бежевый', 'Серый', '', '  ']]);

        $this->assertSame(['Бежевый', 'Серый'], $filters['colors']);
    }

    public function testOverlongColorIsDropped(): void
    {
        $filters = normalizeCatalogFilters(['color' => [str_repeat('a', 101)]]);

        $this->assertSame([], $filters['colors']);
    }

    public function testPriceBoundsAreCastToInt(): void
    {
        $filters = normalizeCatalogFilters(['price_min' => '1000', 'price_max' => '5000.9']);

        $this->assertSame(1000, $filters['price_min']);
        $this->assertSame(5000, $filters['price_max']);
    }

    public function testNegativePriceBoundIsIgnored(): void
    {
        $filters = normalizeCatalogFilters(['price_min' => '-100']);

        $this->assertNull($filters['price_min']);
    }

    public function testNonNumericPriceBoundIsIgnored(): void
    {
        $filters = normalizeCatalogFilters(['price_min' => 'abc']);

        $this->assertNull($filters['price_min']);
    }

    public function testInvertedPriceBoundsAreSwapped(): void
    {
        $filters = normalizeCatalogFilters(['price_min' => '5000', 'price_max' => '1000']);

        $this->assertSame(1000, $filters['price_min']);
        $this->assertSame(5000, $filters['price_max']);
    }

    public function testInStockAcceptsOnlyStringOne(): void
    {
        $this->assertTrue(normalizeCatalogFilters(['in_stock' => '1'])['in_stock']);
        $this->assertFalse(normalizeCatalogFilters(['in_stock' => '0'])['in_stock']);
        $this->assertFalse(normalizeCatalogFilters([])['in_stock']);
    }

    public function testBuildCatalogQueryStringOmitsDefaults(): void
    {
        $filters = normalizeCatalogFilters([]);

        $this->assertSame('', buildCatalogQueryString($filters));
    }

    public function testBuildCatalogQueryStringIncludesActiveFilters(): void
    {
        $filters = normalizeCatalogFilters([
            'sort'      => 'price_asc',
            'category'  => ['3'],
            'color'     => ['Серый'],
            'price_min' => '1000',
            'in_stock'  => '1',
        ]);

        $query = buildCatalogQueryString($filters);
        parse_str($query, $parsed);

        $this->assertSame('price_asc', $parsed['sort']);
        $this->assertSame(['3'], $parsed['category']);
        $this->assertSame(['Серый'], $parsed['color']);
        $this->assertSame('1000', $parsed['price_min']);
        $this->assertSame('1', $parsed['in_stock']);
        $this->assertArrayNotHasKey('price_max', $parsed);
    }

    public function testNormalizeSearchQueryTrimsAndCollapsesWhitespace(): void
    {
        $this->assertSame('диван кровать', normalizeSearchQuery('  диван   кровать  '));
    }

    public function testNormalizeSearchQueryCapsLength(): void
    {
        $this->assertSame(100, mb_strlen(normalizeSearchQuery(str_repeat('a', 150))));
    }

    public function testNormalizeSearchQueryEmptyStringStaysEmpty(): void
    {
        $this->assertSame('', normalizeSearchQuery('   '));
    }

    public function testBuildFulltextTermAppendsWildcardPerWord(): void
    {
        $this->assertSame('диван* кровать*', buildFulltextTerm('диван кровать'));
    }

    public function testBuildFulltextTermStripsBooleanOperators(): void
    {
        $this->assertSame('экокожа*', buildFulltextTerm('эко"кожа'));
    }

    public function testBuildFulltextTermOnlyOperatorsReturnsEmpty(): void
    {
        $this->assertSame('', buildFulltextTerm('+*"'));
    }

    public function testBuildFulltextTermEmptyQueryReturnsEmpty(): void
    {
        $this->assertSame('', buildFulltextTerm(''));
    }
}
