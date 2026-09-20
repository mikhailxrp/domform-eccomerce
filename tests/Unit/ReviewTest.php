<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReviewTest extends TestCase
{
    // ─── normalizeReviewInput() ─────────────────────────────────────────

    public function testNormalizeTrimsAndLowercasesEmail(): void
    {
        $normalized = normalizeReviewInput([
            'name'   => '  Иван  ',
            'email'  => '  Ivan@Example.COM ',
            'rating' => '4',
            'text'   => '  Отличный диван  ',
        ]);

        $this->assertSame('Иван', $normalized['name']);
        $this->assertSame('ivan@example.com', $normalized['email']);
        $this->assertSame(4, $normalized['rating']);
        $this->assertSame('Отличный диван', $normalized['text']);
    }

    public function testNormalizeMissingRatingIsNull(): void
    {
        $normalized = normalizeReviewInput(['name' => 'Иван']);

        $this->assertNull($normalized['rating']);
    }

    public function testNormalizeNonNumericRatingIsNull(): void
    {
        $normalized = normalizeReviewInput(['rating' => 'пять']);

        $this->assertNull($normalized['rating']);
    }

    // ─── validateReviewInput() ───────────────────────────────────────────

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name'   => 'Иван',
            'email'  => 'ivan@example.com',
            'rating' => 5,
            'text'   => 'Отличный диван, всем рекомендую!',
        ], $overrides);
    }

    public function testValidInputHasNoErrors(): void
    {
        $errors = validateReviewInput($this->validInput());

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testNameShorterThanTwoCharsIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['name' => 'И']));

        $this->assertTrue($errors['name']);
    }

    public function testNameLongerThan150CharsIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['name' => str_repeat('а', 151)]));

        $this->assertTrue($errors['name']);
    }

    public function testNameExactly150CharsIsValid(): void
    {
        $errors = validateReviewInput($this->validInput(['name' => str_repeat('а', 150)]));

        $this->assertFalse($errors['name']);
    }

    public function testInvalidEmailFormatIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['email' => 'not-an-email']));

        $this->assertTrue($errors['email']);
    }

    public function testEmptyEmailIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['email' => '']));

        $this->assertTrue($errors['email']);
    }

    public function testRatingZeroIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['rating' => 0]));

        $this->assertTrue($errors['rating']);
    }

    public function testRatingSixIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['rating' => 6]));

        $this->assertTrue($errors['rating']);
    }

    public function testRatingNullIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['rating' => null]));

        $this->assertTrue($errors['rating']);
    }

    public function testEachAllowedRatingIsValid(): void
    {
        foreach ([1, 2, 3, 4, 5] as $rating) {
            $errors = validateReviewInput($this->validInput(['rating' => $rating]));
            $this->assertFalse($errors['rating'], "Рейтинг {$rating} должен быть валиден");
        }
    }

    /**
     * Отзыв о магазине (`/admin/reviews`, форма ручного добавления,
     * `FR-HOME-007` правило 5) не передаёт `product_id` во входной
     * массив вообще — валидатор не должен на него полагаться.
     */
    public function testValidInputWithoutProductIdHasNoErrors(): void
    {
        $errors = validateReviewInput($this->validInput());

        $this->assertFalse(in_array(true, $errors, true));
        $this->assertArrayNotHasKey('product_id', $errors);
    }

    public function testEmptyTextIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['text' => '']));

        $this->assertTrue($errors['text']);
    }

    public function testTextOverLimitIsInvalid(): void
    {
        $errors = validateReviewInput($this->validInput(['text' => str_repeat('а', 2001)]));

        $this->assertTrue($errors['text']);
    }

    public function testTextExactlyAtLimitIsValid(): void
    {
        $errors = validateReviewInput($this->validInput(['text' => str_repeat('а', 2000)]));

        $this->assertFalse($errors['text']);
    }

    // ─── averageRating() ─────────────────────────────────────────────────

    public function testAverageRatingOfEmptyArrayIsNull(): void
    {
        $this->assertNull(averageRating([]));
    }

    public function testAverageRatingRoundsToOneDecimal(): void
    {
        $reviews = [
            ['rating' => 5],
            ['rating' => 4],
            ['rating' => 4],
        ];

        $this->assertSame('4.3', averageRating($reviews));
    }

    public function testAverageRatingOfSingleReview(): void
    {
        $this->assertSame('5.0', averageRating([['rating' => 5]]));
    }

    // ─── reviewStatusLabel() ─────────────────────────────────────────────

    public function testReviewStatusLabelsAreKnown(): void
    {
        $this->assertSame('На модерации', reviewStatusLabel(REVIEW_STATUS_PENDING));
        $this->assertSame('Одобрен', reviewStatusLabel(REVIEW_STATUS_APPROVED));
        $this->assertSame('Отклонён', reviewStatusLabel(REVIEW_STATUS_REJECTED));
    }

    public function testUnknownStatusLabelFallsBackToRawValue(): void
    {
        $this->assertSame('unknown', reviewStatusLabel('unknown'));
    }
}
