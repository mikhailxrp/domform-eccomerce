<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    // ─── validateAddressInput() ─────────────────────────────────────────

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'title'     => 'Дом',
            'city'      => 'Краснодар',
            'street'    => 'Красная',
            'house'     => '10',
            'apartment' => '5',
            'comment'   => 'Домофон 25',
        ], $overrides);
    }

    public function testValidInputHasNoErrors(): void
    {
        $errors = validateAddressInput($this->validInput());

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testEmptyOptionalFieldsAreValid(): void
    {
        $errors = validateAddressInput($this->validInput([
            'title'     => '',
            'apartment' => '',
            'comment'   => '',
        ]));

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testEmptyCityIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['city' => ' ']));

        $this->assertTrue($errors['city']);
    }

    public function testEmptyStreetIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['street' => '']));

        $this->assertTrue($errors['street']);
    }

    public function testEmptyHouseIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['house' => '']));

        $this->assertTrue($errors['house']);
    }

    public function testTooLongCityIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['city' => str_repeat('А', 101)]));

        $this->assertTrue($errors['city']);
    }

    public function testTooLongStreetIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['street' => str_repeat('А', 151)]));

        $this->assertTrue($errors['street']);
    }

    public function testTooLongHouseIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['house' => str_repeat('1', 21)]));

        $this->assertTrue($errors['house']);
    }

    public function testTooLongTitleIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['title' => str_repeat('А', 101)]));

        $this->assertTrue($errors['title']);
    }

    public function testTooLongApartmentIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['apartment' => str_repeat('1', 21)]));

        $this->assertTrue($errors['apartment']);
    }

    public function testTooLongCommentIsInvalid(): void
    {
        $errors = validateAddressInput($this->validInput(['comment' => str_repeat('А', 256)]));

        $this->assertTrue($errors['comment']);
    }

    // ─── formatAddress() ─────────────────────────────────────────────────

    public function testFormatAddressWithApartmentAndComment(): void
    {
        $formatted = formatAddress([
            'city'      => 'Краснодар',
            'street'    => 'Красная',
            'house'     => '10',
            'apartment' => '5',
            'comment'   => 'подъезд 2',
        ]);

        $this->assertSame('г. Краснодар, ул. Красная, д. 10, кв. 5 (подъезд 2)', $formatted);
    }

    public function testFormatAddressWithoutApartmentAndComment(): void
    {
        $formatted = formatAddress([
            'city'      => 'Краснодар',
            'street'    => 'Красная',
            'house'     => '10',
            'apartment' => null,
            'comment'   => null,
        ]);

        $this->assertSame('г. Краснодар, ул. Красная, д. 10', $formatted);
    }

    public function testFormatAddressWithApartmentOnly(): void
    {
        $formatted = formatAddress([
            'city'      => 'Краснодар',
            'street'    => 'Красная',
            'house'     => '10',
            'apartment' => '5',
            'comment'   => null,
        ]);

        $this->assertSame('г. Краснодар, ул. Красная, д. 10, кв. 5', $formatted);
    }

    public function testFormatAddressWithCommentOnly(): void
    {
        $formatted = formatAddress([
            'city'      => 'Краснодар',
            'street'    => 'Красная',
            'house'     => '10',
            'apartment' => null,
            'comment'   => 'домофон 25',
        ]);

        $this->assertSame('г. Краснодар, ул. Красная, д. 10 (домофон 25)', $formatted);
    }
}
