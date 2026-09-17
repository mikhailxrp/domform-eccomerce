<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testMiddlePage(): void
    {
        $result = buildPagination(30, 2, 12);

        $this->assertSame(2, $result['page']);
        $this->assertSame(3, $result['total_pages']);
        $this->assertTrue($result['has_prev']);
        $this->assertTrue($result['has_next']);
        $this->assertSame(1, $result['prev_page']);
        $this->assertSame(3, $result['next_page']);
        $this->assertSame(12, $result['offset']);
    }

    public function testFirstPageHasNoPrev(): void
    {
        $result = buildPagination(30, 1, 12);

        $this->assertFalse($result['has_prev']);
        $this->assertNull($result['prev_page']);
        $this->assertSame(0, $result['offset']);
    }

    public function testLastPageHasNoNext(): void
    {
        $result = buildPagination(30, 3, 12);

        $this->assertFalse($result['has_next']);
        $this->assertNull($result['next_page']);
    }

    public function testPageBelowOneNormalizesToOne(): void
    {
        $result = buildPagination(30, 0, 12);
        $this->assertSame(1, $result['page']);

        $result = buildPagination(30, -5, 12);
        $this->assertSame(1, $result['page']);
    }

    public function testPageAboveTotalNormalizesToLastPage(): void
    {
        $result = buildPagination(30, 999, 12);

        $this->assertSame(3, $result['page']);
        $this->assertFalse($result['has_next']);
    }

    public function testEmptyResultSetHasSinglePage(): void
    {
        $result = buildPagination(0, 1, 12);

        $this->assertSame(1, $result['total_pages']);
        $this->assertSame(1, $result['page']);
        $this->assertFalse($result['has_prev']);
        $this->assertFalse($result['has_next']);
    }

    public function testBuildPaginationUrlAppendsPage(): void
    {
        $this->assertSame('/catalog?page=2', buildPaginationUrl('/catalog', [], 2));
    }

    public function testBuildPaginationUrlPreservesExistingParams(): void
    {
        $url = buildPaginationUrl('/catalog', ['sort' => 'price_asc'], 3);

        $this->assertSame('/catalog?sort=price_asc&page=3', $url);
    }

    public function testBuildPaginationUrlOverridesExistingPageParam(): void
    {
        $url = buildPaginationUrl('/catalog', ['page' => 1, 'sort' => 'price_asc'], 5);

        $this->assertSame('/catalog?page=5&sort=price_asc', $url);
    }
}
