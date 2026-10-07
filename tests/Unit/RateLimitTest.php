<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RateLimitTest extends TestCase
{
    private string $action = 'test-action-phpunit';

    protected function tearDown(): void
    {
        clearRateLimit($this->action);
    }

    public function testAllowsUntilLimitReached(): void
    {
        $this->assertFalse(tooManyAttempts($this->action, 3, 60));

        hitRateLimit($this->action);
        hitRateLimit($this->action);
        $this->assertFalse(tooManyAttempts($this->action, 3, 60));

        hitRateLimit($this->action);
        $this->assertTrue(tooManyAttempts($this->action, 3, 60));
    }

    public function testExpiredWindowRestartsCounterWhenDecayIsPassed(): void
    {
        $path = rateLimitStoragePath($this->action);
        file_put_contents($path, json_encode(['count' => 99, 'first_at' => time() - 120]));

        hitRateLimit($this->action, 60);

        $this->assertFalse(tooManyAttempts($this->action, 2, 60));
    }

    public function testWindowIsKeptWhenDecayIsNotPassed(): void
    {
        $path = rateLimitStoragePath($this->action);
        file_put_contents($path, json_encode(['count' => 99, 'first_at' => time() - 120]));

        hitRateLimit($this->action);

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertSame(100, $data['count']);
    }

    public function testClearRateLimitResetsCounter(): void
    {
        hitRateLimit($this->action);
        hitRateLimit($this->action);
        clearRateLimit($this->action);

        $this->assertFalse(tooManyAttempts($this->action, 1, 60));
    }
}
