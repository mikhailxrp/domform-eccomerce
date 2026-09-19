<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testTransliteratesCyrillicName(): void
    {
        $this->assertSame('divan-modern-2-h-mestnyy', slugify('Диван «Модерн» 2-х местный'));
    }

    public function testKeepsLatinAndDigits(): void
    {
        $this->assertSame('stol-provans-xl', slugify('Стол Прованс XL'));
    }

    public function testCollapsesRepeatedSeparators(): void
    {
        $this->assertSame('divan-uglovoy', slugify('Диван -- Угловой'));
    }

    public function testTrimsLeadingAndTrailingSeparators(): void
    {
        $this->assertSame('shkaf', slugify('  -Шкаф- '));
    }

    public function testOnlySpecialCharactersProduceEmptyString(): void
    {
        $this->assertSame('', slugify('!!!  ---  ???'));
    }

    public function testEmptyInputProducesEmptyString(): void
    {
        $this->assertSame('', slugify(''));
    }

    public function testMixedCaseIsLowercased(): void
    {
        $this->assertSame('divan', slugify('ДИВАН'));
    }
}
