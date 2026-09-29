<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testStrongPasswordIsAccepted(): void
    {
        self::assertSame([], PasswordPolicy::validate('Segura#2026Ab'));
    }

    public function testShortPasswordIsRejected(): void
    {
        self::assertNotEmpty(PasswordPolicy::validate('Aa1#'));
    }

    public function testPasswordWithoutSymbolIsRejected(): void
    {
        self::assertNotEmpty(PasswordPolicy::validate('Password2026A'));
    }
}
