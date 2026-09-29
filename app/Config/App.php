<?php
declare(strict_types=1);

namespace App\Config;

final class App
{
    public static function name(): string
    {
        return $_ENV['APP_NAME'] ?? 'ICBF Reparto';
    }

    public static function env(): string
    {
        return $_ENV['APP_ENV'] ?? 'production';
    }

    public static function isProduction(): bool
    {
        return self::env() === 'production';
    }

    public static function url(string $path = '/'): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $base . '/' . ltrim($path, '/');
    }
}
