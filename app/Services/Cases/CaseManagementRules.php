<?php
declare(strict_types=1);

namespace App\Services\Cases;

final class CaseManagementRules
{
    /** @return list<string> */
    public static function allowedManagementTypes(): array
    {
        return [
            'CLOSED',
            'DIRECTED',
            'ESCALATED',
            'PETITION_TYPE_CHANGE',
            'POLICE_REPORT',
        ];
    }

    public static function requiresEscalationCategory(string $managementType): bool
    {
        return strtoupper(trim($managementType)) === 'ESCALATED';
    }

    public static function requiresNewPetitionType(string $managementType): bool
    {
        return strtoupper(trim($managementType)) === 'PETITION_TYPE_CHANGE';
    }

    public static function closesCase(string $managementType): bool
    {
        return strtoupper(trim($managementType)) === 'CLOSED';
    }
}
