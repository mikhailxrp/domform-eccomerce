<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ContentTest extends TestCase
{
    // ─── isContentPageSlug() ─────────────────────────────────────────────

    public function testKnownSlugsAreAccepted(): void
    {
        foreach (CONTENT_PAGE_SLUGS as $slug) {
            $this->assertTrue(isContentPageSlug($slug), $slug);
        }
        $this->assertCount(7, CONTENT_PAGE_SLUGS);
    }

    public function testUnknownAndMalformedSlugsAreRejected(): void
    {
        $this->assertFalse(isContentPageSlug('nope'));
        $this->assertFalse(isContentPageSlug('About'));
        $this->assertFalse(isContentPageSlug('../etc'));
        $this->assertFalse(isContentPageSlug('about/'));
        $this->assertFalse(isContentPageSlug(''));
    }

    // ─── renderContentBody() ─────────────────────────────────────────────

    public function testEmptyAndWhitespaceOnlyBodyRendersNothing(): void
    {
        $this->assertSame('', renderContentBody(''));
        $this->assertSame('', renderContentBody("  \n\n \r\n "));
    }

    public function testParagraphsAreSplitByBlankLines(): void
    {
        $this->assertSame(
            "<p>Первый абзац.</p>\n<p>Второй абзац.</p>",
            renderContentBody("Первый абзац.\n\nВторой абзац.")
        );
    }

    public function testLineBreaksInsideParagraphBecomeBr(): void
    {
        $this->assertSame(
            '<p>Строка один<br>Строка два</p>',
            renderContentBody("Строка один\nСтрока два")
        );
    }

    public function testWindowsLineEndingsAreNormalized(): void
    {
        $this->assertSame(
            "<p>Один</p>\n<p>Два<br>Три</p>",
            renderContentBody("Один\r\n\r\nДва\r\nТри")
        );
    }

    public function testHeadingLineRendersH2(): void
    {
        $this->assertSame('<h2>Доставка</h2>', renderContentBody('## Доставка'));
    }

    public function testHeadingFollowedByTextWithoutBlankLineStillSplits(): void
    {
        $this->assertSame(
            "<h2>Доставка</h2>\n<p>По городу — бесплатно.</p>",
            renderContentBody("## Доставка\nПо городу — бесплатно.")
        );
    }

    public function testListBlockRendersUl(): void
    {
        $this->assertSame(
            '<ul><li>Наличными</li><li>Переводом</li></ul>',
            renderContentBody("- Наличными\n- Переводом")
        );
    }

    public function testMixedDocumentKeepsBlockOrder(): void
    {
        $body = "## Оплата\n\nТри способа:\n\n- Картой\n- Наличными\n\nОстаток — при получении.";

        $this->assertSame(
            "<h2>Оплата</h2>\n<p>Три способа:</p>\n<ul><li>Картой</li><li>Наличными</li></ul>\n<p>Остаток — при получении.</p>",
            renderContentBody($body)
        );
    }

    public function testBlockWithOnlySomeListLinesIsAParagraph(): void
    {
        // Смешанный блок — не список: иначе строка без `- ` молча
        // потерялась бы или превратилась в пункт.
        $this->assertSame(
            '<p>Способы:<br>- Картой</p>',
            renderContentBody("Способы:\n- Картой")
        );
    }

    public function testHtmlInBodyIsEscapedEverywhere(): void
    {
        $body = "## <b>x</b>\n\n<script>alert(1)</script>\n\n- <i>y</i>";

        $this->assertSame(
            "<h2>&lt;b&gt;x&lt;/b&gt;</h2>\n<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>\n<ul><li>&lt;i&gt;y&lt;/i&gt;</li></ul>",
            renderContentBody($body)
        );
    }

    public function testQuotesAreEscaped(): void
    {
        $this->assertSame(
            '<p>Скажи &quot;привет&quot; &amp; &#039;пока&#039;</p>',
            renderContentBody("Скажи \"привет\" & 'пока'")
        );
    }

    // ─── validateContentPageInput() ────────────────────────────────────────

    public function testValidInputHasNoErrors(): void
    {
        $errors = validateContentPageInput(['title' => 'О компании', 'body' => 'Текст страницы.']);

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testEmptyTitleIsInvalid(): void
    {
        $errors = validateContentPageInput(['title' => '', 'body' => 'Текст.']);

        $this->assertTrue($errors['title']);
    }

    public function testTitleLongerThan200CharsIsInvalid(): void
    {
        $errors = validateContentPageInput(['title' => str_repeat('а', 201), 'body' => 'Текст.']);

        $this->assertTrue($errors['title']);
    }

    public function testTitleExactly200CharsIsValid(): void
    {
        $errors = validateContentPageInput(['title' => str_repeat('а', 200), 'body' => 'Текст.']);

        $this->assertFalse($errors['title']);
    }

    public function testEmptyBodyIsInvalid(): void
    {
        $errors = validateContentPageInput(['title' => 'Заголовок', 'body' => '']);

        $this->assertTrue($errors['body']);
    }

    public function testWhitespaceOnlyBodyIsInvalid(): void
    {
        $errors = validateContentPageInput(['title' => 'Заголовок', 'body' => "  \n  "]);

        $this->assertTrue($errors['body']);
    }

    // ─── publicUrlForContentSlug() ──────────────────────────────────────────

    public function testAboutShowroomContactsHaveOwnRoutes(): void
    {
        $this->assertSame('/about', publicUrlForContentSlug('about'));
        $this->assertSame('/showroom', publicUrlForContentSlug('showroom'));
        $this->assertSame('/contacts', publicUrlForContentSlug('contacts'));
    }

    public function testOtherSlugsUseGenericPagesRoute(): void
    {
        $this->assertSame('/pages/delivery-payment', publicUrlForContentSlug('delivery-payment'));
        $this->assertSame('/pages/return-warranty', publicUrlForContentSlug('return-warranty'));
        $this->assertSame('/pages/offer', publicUrlForContentSlug('offer'));
        $this->assertSame('/pages/privacy-policy', publicUrlForContentSlug('privacy-policy'));
    }
}
