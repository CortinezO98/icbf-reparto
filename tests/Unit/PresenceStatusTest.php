<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Presence\PresenceStatus;
use PHPUnit\Framework\TestCase;

final class PresenceStatusTest extends TestCase
{
    public function testOnlyAvailableIsAssignable(): void
    {
        self::assertTrue(PresenceStatus::isAssignable('AVAILABLE'));

        foreach (PresenceStatus::selectableCodes() as $code) {
            if ($code === 'AVAILABLE') {
                continue;
            }

            self::assertFalse(PresenceStatus::isAssignable($code));
        }
    }

    public function testScopeContainsNineSelectableStatuses(): void
    {
        self::assertCount(9, PresenceStatus::selectableCodes());
        self::assertTrue(PresenceStatus::isSelectable('TECH_FAILURE'));
        self::assertFalse(PresenceStatus::isSelectable('OFFLINE'));
    }
}
