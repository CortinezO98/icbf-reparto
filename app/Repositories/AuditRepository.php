<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(
        ?int $actorUserId,
        string $eventType,
        string $entityType,
        ?string $entityId = null,
        array $details = []
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO audit_log
             (actor_user_id, event_type, entity_type, entity_id, ip_address, user_agent, details_json, created_at)
             VALUES
             (:actor_user_id, :event_type, :entity_type, :entity_id, :ip_address, :user_agent, :details_json, NOW(6))'
        );

        $st->execute([
            ':actor_user_id' => $actorUserId,
            ':event_type' => strtoupper($eventType),
            ':entity_type' => strtoupper($entityType),
            ':entity_id' => $entityId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ':details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
