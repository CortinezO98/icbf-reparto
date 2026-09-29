<?php
declare(strict_types=1);

namespace App\Auth;

use PDO;

final class Authorization
{
    public static function hasPermission(PDO $pdo, int $userId, string $permission): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM user_roles ur
            JOIN role_permissions rp ON rp.role_id = ur.role_id
            JOIN permissions p ON p.id = rp.permission_id
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
              AND p.code = :permission
              AND p.is_active = 1
              AND r.is_active = 1
            LIMIT 1
        SQL;

        $st = $pdo->prepare($sql);
        $st->execute([
            ':uid' => $userId,
            ':permission' => strtoupper(trim($permission)),
        ]);

        return (bool)$st->fetchColumn();
    }

    public static function requirePermission(PDO $pdo, string $permission): void
    {
        Auth::requireLogin();
        $uid = (int)Auth::id();

        if (!self::hasPermission($pdo, $uid, $permission)) {
            http_response_code(403);
            exit('No autorizado.');
        }
    }

    /** @return list<string> */
    public static function roles(PDO $pdo, int $userId): array
    {
        $st = $pdo->prepare(
            'SELECT r.code FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid AND r.is_active = 1
             ORDER BY r.code'
        );
        $st->execute([':uid' => $userId]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
