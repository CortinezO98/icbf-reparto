<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cases\CaseManagementRules;
use PHPUnit\Framework\TestCase;

final class CaseManagementRulesTest extends TestCase
{
    public function testManagementRules(): void
    {
        self::assertTrue(CaseManagementRules::closesCase('CLOSED'));
        self::assertTrue(CaseManagementRules::requiresEscalationCategory('ESCALATED'));
        self::assertTrue(CaseManagementRules::requiresNewPetitionType('PETITION_TYPE_CHANGE'));
        self::assertFalse(CaseManagementRules::closesCase('DIRECTED'));
    }
}
