<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ShiftRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function upcoming(int $limit = 200): array
    {
        $st = $this->pdo->prepare(
            "SELECT
                s.id,
                s.user_id,
                s.queue_id,
                s.starts_at,
                s.ends_at,
                s.is_active,
                u.full_name,
                u.username,
                q.name queue_name,
                q.code queue_code
             FROM agent_shifts s
             JOIN users u ON u.id=s.user_id
             LEFT JOIN work_queues q ON q.id=s.queue_id
             WHERE s.is_active=1
               AND u.is_active=1
             ORDER BY s.starts_at ASC,s.id ASC
             LIMIT :limit"
        );
        $st->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function agents(): array
    {
        return $this->pdo->query(
            "SELECT DISTINCT u.id,u.full_name,u.username
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id
              AND r.code='AGENTE'
              AND r.is_active=1
             WHERE u.is_active=1
             ORDER BY u.full_name,u.id"
        )->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function queues(): array
    {
        return $this->pdo->query(
            "SELECT id,code,name
             FROM work_queues
             WHERE is_active=1
             ORDER BY priority,code"
        )->fetchAll() ?: [];
    }

    public function create(
        int $userId,
        ?int $queueId,
        string $startsAt,
        string $endsAt,
        int $createdBy
    ): int {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('La hora de finalización debe ser posterior a la de inicio.');
        }

        $st = $this->pdo->prepare(
            "INSERT INTO agent_shifts
             (user_id,queue_id,starts_at,ends_at,is_active,created_by,created_at,updated_at)
             VALUES(:user_id,:queue_id,:starts_at,:ends_at,1,:created_by,NOW(6),NOW(6))"
        );
        $st->execute([
            ':user_id'=>$userId,
            ':queue_id'=>$queueId,
            ':starts_at'=>$startsAt,
            ':ends_at'=>$endsAt,
            ':created_by'=>$createdBy,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function deactivate(int $id): void
    {
        $st = $this->pdo->prepare(
            "UPDATE agent_shifts
             SET is_active=0,updated_at=NOW(6)
             WHERE id=:id"
        );
        $st->execute([':id'=>$id]);
    }
}
