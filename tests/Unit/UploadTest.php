<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UploadTest extends TestCase
{
    public function testNoFileIsRejected(): void
    {
        $this->assertSame('Выберите файл.', validateUploadedImage(['error' => UPLOAD_ERR_NO_FILE], ''));
    }

    public function testUploadErrorIsRejected(): void
    {
        $file = ['error' => UPLOAD_ERR_PARTIAL, 'size' => 100];
        $this->assertNotNull(validateUploadedImage($file, 'image/jpeg'));
    }

    public function testTooLargeFileIsRejected(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'size' => UPLOAD_MAX_BYTES + 1];
        $this->assertNotNull(validateUploadedImage($file, 'image/jpeg'));
    }

    public function testZeroSizeFileIsRejected(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 0];
        $this->assertNotNull(validateUploadedImage($file, 'image/jpeg'));
    }

    public function testDisallowedMimeIsRejected(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1000];
        $this->assertNotNull(validateUploadedImage($file, 'application/x-php'));
    }

    public function testRenamedPhpFileIsRejectedByDetectedMime(): void
    {
        // .jpg по имени/расширению, но реальный MIME — не картинка
        // (типичный обход: .php переименован в .jpg).
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1000, 'name' => 'shell.jpg'];
        $this->assertNotNull(validateUploadedImage($file, 'text/x-php'));
    }

    public function testValidJpegIsAccepted(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1000];
        $this->assertNull(validateUploadedImage($file, 'image/jpeg'));
    }

    public function testValidPngIsAccepted(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1000];
        $this->assertNull(validateUploadedImage($file, 'image/png'));
    }

    public function testValidWebpIsAccepted(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1000];
        $this->assertNull(validateUploadedImage($file, 'image/webp'));
    }

    public function testOversizedDimensionsAreRejected(): void
    {
        // Маленький файл (проходит UPLOAD_MAX_BYTES), но огромное
        // разрешение — типичная decompression bomb для GD-ресайза.
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1000];
        $this->assertNotNull(
            validateUploadedImage($file, 'image/jpeg', [UPLOAD_MAX_DIMENSION + 1, 100])
        );
        $this->assertNotNull(
            validateUploadedImage($file, 'image/jpeg', [100, UPLOAD_MAX_DIMENSION + 1])
        );
    }

    public function testDimensionsWithinLimitAreAccepted(): void
    {
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1000];
        $this->assertNull(
            validateUploadedImage($file, 'image/jpeg', [UPLOAD_MAX_DIMENSION, UPLOAD_MAX_DIMENSION])
        );
    }

    public function testMissingDimensionsAreNotRejectedByDimensionCheck(): void
    {
        // `null` — размер не определён внешним вызовом (например,
        // `getimagesize()` не смог его прочитать); проверка размера не
        // должна сама по себе блокировать файл — этим занимаются
        // остальные проверки (MIME, размер в байтах).
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 1000];
        $this->assertNull(validateUploadedImage($file, 'image/jpeg', null));
    }

    public function testExtensionForKnownMime(): void
    {
        $this->assertSame('jpg', uploadExtensionForMime('image/jpeg'));
        $this->assertSame('png', uploadExtensionForMime('image/png'));
        $this->assertSame('webp', uploadExtensionForMime('image/webp'));
    }

    public function testExtensionForUnknownMimeIsNull(): void
    {
        $this->assertNull(uploadExtensionForMime('application/pdf'));
    }
}
