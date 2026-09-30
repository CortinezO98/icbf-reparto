<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\PasswordPolicy;
use App\Services\Users\TemporaryPasswordGenerator;
use PHPUnit\Framework\TestCase;

final class TemporaryPasswordGeneratorTest extends TestCase
{
    public function testGeneratedPasswordMeetsCurrentPolicy(): void
    {
        $password = TemporaryPasswordGenerator::generate();

        self::assertGreaterThanOrEqual(12, mb_strlen($password));
        self::assertSame([], PasswordPolicy::validate($password));
    }
}
