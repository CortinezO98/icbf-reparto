<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyMaximumLengthTest extends TestCase
{
    public function testRejectsExcessivelyLongPassword(): void
    {
        $password = 'Aa1!' . str_repeat('x', 125);

        $errors = PasswordPolicy::validate($password);

        self::assertContains(
            'La contraseña no puede tener más de 128 caracteres.',
            $errors
        );
    }

    public function testAcceptsStrongPasswordWithinMaximumLength(): void
    {
        self::assertSame([], PasswordPolicy::validate('Segura-ICBF-2026!'));
    }
}
