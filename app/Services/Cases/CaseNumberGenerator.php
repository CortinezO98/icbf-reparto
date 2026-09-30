<?php
declare(strict_types=1);

namespace App\Services\Cases;

final class CaseNumberGenerator
{
    public static function generate(): string
    {
        return sprintf(
            'REP-%s-%s',
            date('YmdHis'),
            strtoupper(bin2hex(random_bytes(4)))
        );
    }
}
