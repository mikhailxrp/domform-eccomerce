<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    // ─── phoneToTel() ───────────────────────────────────────────────────

    public function testPhoneToTelFormatsDisplayPhone(): void
    {
        $this->assertSame('+79000000000', phoneToTel('+7 900 000-00-00'));
    }

    public function testPhoneToTelAcceptsPhoneStartingWithEight(): void
    {
        $this->assertSame('+79000000000', phoneToTel('8 (900) 000-00-00'));
    }

    public function testPhoneToTelMatchesNormalizePhone(): void
    {
        $this->assertSame(normalizePhone('+7 900 111-22-33'), phoneToTel('+7 900 111-22-33'));
    }

    // ─── validateSettingsInput() ────────────────────────────────────────

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'shop_phone'        => '+7 900 000-00-00',
            'shop_whatsapp_url' => 'https://wa.me/79000000000',
            'shop_email'        => 'info@domform.ru',
            'workshop_address'  => 'г. Краснодар, ул. Промышленная, 1',
            'work_hours'        => 'Пн–Сб, 9:00–19:00',
            'map_embed_url'     => 'https://yandex.ru/map-widget/v1/...',
        ], $overrides);
    }

    public function testValidInputHasNoErrors(): void
    {
        $errors = validateSettingsInput($this->validInput());

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testEmptyMapEmbedUrlIsValid(): void
    {
        $errors = validateSettingsInput($this->validInput(['map_embed_url' => '']));

        $this->assertFalse(in_array(true, $errors, true));
    }

    public function testInvalidPhoneIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['shop_phone' => '123']));

        $this->assertTrue($errors['shop_phone']);
    }

    public function testWhatsappUrlWithoutHttpsIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['shop_whatsapp_url' => 'http://wa.me/79000000000']));

        $this->assertTrue($errors['shop_whatsapp_url']);
    }

    public function testEmptyWhatsappUrlIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['shop_whatsapp_url' => '']));

        $this->assertTrue($errors['shop_whatsapp_url']);
    }

    public function testInvalidEmailIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['shop_email' => 'not-an-email']));

        $this->assertTrue($errors['shop_email']);
    }

    public function testEmptyWorkshopAddressIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['workshop_address' => '  ']));

        $this->assertTrue($errors['workshop_address']);
    }

    public function testTooLongWorkshopAddressIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['workshop_address' => str_repeat('А', 256)]));

        $this->assertTrue($errors['workshop_address']);
    }

    public function testEmptyWorkHoursIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['work_hours' => '']));

        $this->assertTrue($errors['work_hours']);
    }

    public function testTooLongWorkHoursIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['work_hours' => str_repeat('А', 101)]));

        $this->assertTrue($errors['work_hours']);
    }

    public function testMapEmbedUrlWithoutHttpsIsRejected(): void
    {
        $errors = validateSettingsInput($this->validInput(['map_embed_url' => 'http://yandex.ru/map']));

        $this->assertTrue($errors['map_embed_url']);
    }
}
