<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BannerFormTest extends TestCase
{
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'title'      => 'Новинки сезона',
            'link'       => '/catalog',
            'sort_order' => 1,
        ], $overrides);
    }

    public function testValidInputWithImageHasNoErrors(): void
    {
        $errors = validateBannerInput($this->validInput(), true);

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testMissingImageIsInvalid(): void
    {
        $errors = validateBannerInput($this->validInput(), false);

        $this->assertTrue($errors['image']);
    }

    public function testEmptyTitleIsValid(): void
    {
        $errors = validateBannerInput($this->validInput(['title' => '']), true);

        $this->assertFalse($errors['title']);
    }

    public function testTitleLongerThan200CharsIsInvalid(): void
    {
        $errors = validateBannerInput($this->validInput(['title' => str_repeat('а', 201)]), true);

        $this->assertTrue($errors['title']);
    }

    public function testTitleExactly200CharsIsValid(): void
    {
        $errors = validateBannerInput($this->validInput(['title' => str_repeat('а', 200)]), true);

        $this->assertFalse($errors['title']);
    }

    public function testEmptyLinkIsValid(): void
    {
        $errors = validateBannerInput($this->validInput(['link' => '']), true);

        $this->assertFalse($errors['link']);
    }

    public function testRelativeLinkIsValid(): void
    {
        $errors = validateBannerInput($this->validInput(['link' => '/catalog/divany']), true);

        $this->assertFalse($errors['link']);
    }

    public function testHttpsLinkIsValid(): void
    {
        $errors = validateBannerInput($this->validInput(['link' => 'https://example.com/promo']), true);

        $this->assertFalse($errors['link']);
    }

    public function testHttpLinkIsInvalid(): void
    {
        $errors = validateBannerInput($this->validInput(['link' => 'http://example.com']), true);

        $this->assertTrue($errors['link']);
    }

    public function testJavascriptLinkIsInvalid(): void
    {
        $errors = validateBannerInput($this->validInput(['link' => 'javascript:alert(1)']), true);

        $this->assertTrue($errors['link']);
    }

    public function testNegativeSortOrderIsInvalid(): void
    {
        $errors = validateBannerInput($this->validInput(['sort_order' => -1]), true);

        $this->assertTrue($errors['sort_order']);
    }

    public function testZeroSortOrderIsValid(): void
    {
        $errors = validateBannerInput($this->validInput(['sort_order' => 0]), true);

        $this->assertFalse($errors['sort_order']);
    }
}
