<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testCurrentUserReturnsNullWhenNotAuthenticated(): void
    {
        $_SESSION = [];

        $this->assertNull(currentUser());
    }

    public function testCurrentUserReturnsSessionDataWhenAuthenticated(): void
    {
        $_SESSION['user_id']   = 5;
        $_SESSION['user_name'] = 'Иван';
        $_SESSION['user_role'] = 'customer';

        $this->assertSame(
            ['id' => 5, 'name' => 'Иван', 'role' => 'customer'],
            currentUser()
        );
    }

    public function testCurrentUserAcceptsNumericStringUserId(): void
    {
        $_SESSION['user_id']   = '7';
        $_SESSION['user_name'] = 'Пётр';
        $_SESSION['user_role'] = 'admin';

        $this->assertSame(
            ['id' => 7, 'name' => 'Пётр', 'role' => 'admin'],
            currentUser()
        );
    }

    public function testCurrentUserIgnoresInvalidUserId(): void
    {
        $_SESSION['user_id'] = 0;

        $this->assertNull(currentUser());
    }

    public function testRoleAllowedAcceptsMatchingRole(): void
    {
        $this->assertTrue(roleAllowed(['manager', 'admin'], 'admin'));
    }

    public function testRoleAllowedRejectsNonMatchingRole(): void
    {
        $this->assertFalse(roleAllowed(['manager', 'admin'], 'customer'));
    }

    public function testRoleAllowedRejectsNullRole(): void
    {
        $this->assertFalse(roleAllowed(['manager', 'admin'], null));
    }

    public function testRoleAllowedRejectsEmptyRole(): void
    {
        $this->assertFalse(roleAllowed(['manager', 'admin'], ''));
    }
}
