<?php
declare(strict_types=1);

namespace App\Services\Users;

final class QueueSelection
{
    public static function meansAll(string $value): bool
    {
        $normalized = mb_strtoupper(trim($value));

        return in_array(
            $normalized,
            [
                'ALL',
                'TODAS',
                'TODOS',
                'TODAS LAS COLAS',
                'TODAS_LAS_COLAS',
                '*',
            ],
            true
        );
    }
}
