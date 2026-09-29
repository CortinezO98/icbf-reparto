<?php
declare(strict_types=1);

namespace App\Services\Import;

final class HeaderNormalizer
{
    public static function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /** @param list<string> $headers */
    public static function signature(array $headers): string
    {
        $normalized = array_map([self::class,'normalize'], $headers);
        return hash('sha256', implode('|', $normalized));
    }
}
