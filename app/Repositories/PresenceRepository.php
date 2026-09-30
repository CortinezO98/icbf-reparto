<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PresenceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function currentForUser(int $userId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT *
             FROM agent_presence
             WHERE user_id=:uid AND ended_at IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->execute([':uid'=>$userId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function setStatus(
        int $userId,
        string $statusCode,
        int $setBy,
        string $source = 'USER',
        ?string $notes = null
    ): int {
        $this->pdo->beginTransaction();

        try {
            $st = $this->pdo->prepare(
                "SELECT id
                 FROM agent_presence
                 WHERE user_id=:uid AND ended_at IS NULL
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE"
            );
            $st->execute([':uid'=>$userId]);
            $currentId = $st->fetchColumn();

            if ($currentId !== false) {
                $close = $this->pdo->prepare(
                    "UPDATE agent_presence
                     SET ended_at=NOW(6), last_heartbeat_at=NOW(6)
                     WHERE id=:id"
                );
                $close->execute([':id'=>(int)$currentId]);
            }

            $insert = $this->pdo->prepare(
                "INSERT INTO agent_presence
                 (user_id,status_code,started_at,last_heartbeat_at,notes,source,set_by)
                 VALUES(:uid,:status,NOW(6),NOW(6),:notes,:source,:set_by)"
            );
            $insert->execute([
                ':uid'=>$userId,
                ':status'=>strtoupper(trim($statusCode)),
                ':notes'=>$notes,
                ':source'=>$source,
                ':set_by'=>$setBy,
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function heartbeat(int $userId): void
    {
        $st = $this->pdo->prepare(
            "UPDATE agent_presence
             SET last_heartbeat_at=NOW(6)
             WHERE user_id=:uid AND ended_at IS NULL"
        );
        $st->execute([':uid'=>$userId]);
    }
}
