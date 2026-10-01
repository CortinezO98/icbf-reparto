<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Cases\CaseNumberGenerator;
use PDO;

final class CaseRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO cases
             (case_number,external_key,queue_id,source_structure_version_id,
              source_batch_id,source_batch_row_id,petition_type,regional,segment,
              origin_channel,radicated_at,current_state,created_by)
             VALUES
             (:case_number,:external_key,:queue_id,:version_id,:batch_id,:row_id,
              :petition_type,:regional,:segment,:origin_channel,:radicated_at,
              'PENDING_ASSIGNMENT',:created_by)"
        );

        $st->execute([
            ':case_number'=>CaseNumberGenerator::generate(),
            ':external_key'=>(string)$data['external_key'],
            ':queue_id'=>$data['queue_id'] ?? null,
            ':version_id'=>$data['source_structure_version_id'] ?? null,
            ':batch_id'=>$data['source_batch_id'] ?? null,
            ':row_id'=>$data['source_batch_row_id'] ?? null,
            ':petition_type'=>$data['petition_type'] ?? null,
            ':regional'=>$data['regional'] ?? null,
            ':segment'=>$data['segment'] ?? null,
            ':origin_channel'=>$data['origin_channel'] ?? null,
            ':radicated_at'=>$data['radicated_at'] ?? null,
            ':created_by'=>$data['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findByExternalKeyForUpdate(string $externalKey): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT *
             FROM cases
             WHERE external_key=:external_key
             LIMIT 1
             FOR UPDATE"
        );
        $st->execute([':external_key'=>$externalKey]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function addCreatedEvent(int $caseId, ?int $actorUserId, int $batchId): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO case_events
             (case_id,actor_user_id,event_type,from_state,to_state,details_json,created_at)
             VALUES
             (:case_id,:actor,'CASE_CREATED',NULL,'PENDING_ASSIGNMENT',:details,NOW(6))"
        );
        $st->execute([
            ':case_id'=>$caseId,
            ':actor'=>$actorUserId,
            ':details'=>json_encode(
                ['source'=>'IMPORT_BATCH','batch_id'=>$batchId],
                JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
            ),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function assignedToUser(int $userId, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        $st = $this->pdo->prepare(
            "SELECT c.*,q.name queue_name
             FROM cases c
             LEFT JOIN work_queues q ON q.id=c.queue_id
             WHERE c.assigned_user_id=:uid
               AND c.closed_at IS NULL
             ORDER BY c.assigned_at,c.id
             LIMIT {$limit}"
        );
        $st->execute([':uid'=>$userId]);

        return $st->fetchAll() ?: [];
    }
}
