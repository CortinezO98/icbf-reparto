<?php
declare(strict_types=1);

namespace App\Services\Presence;

final class PresenceStatus
{
    /** @return list<string> */
    public static function selectableCodes(): array
    {
        return [
            'AVAILABLE',
            'TRAINING',
            'MEETING',
            'BREAK',
            'ASYNC_ACTIVITY',
            'BATHROOM',
            'TECH_FAILURE',
            'FEEDBACK',
            'ACTIVE_BREAK',
        ];
    }

    public static function isSelectable(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::selectableCodes(), true);
    }

    public static function isAssignable(string $code): bool
    {
        return strtoupper(trim($code)) === 'AVAILABLE';
    }

    public static function color(string $code): string
    {
        return match (strtoupper(trim($code))) {
            'AVAILABLE' => '#22c55e',
            'TRAINING' => '#f59e0b',
            'MEETING' => '#3b82f6',
            'BREAK' => '#f97316',
            'ASYNC_ACTIVITY' => '#8b5cf6',
            'BATHROOM' => '#eab308',
            'TECH_FAILURE' => '#ef4444',
            'FEEDBACK' => '#14b8a6',
            'ACTIVE_BREAK' => '#06b6d4',
            default => '#94a3b8',
        };
    }
}
