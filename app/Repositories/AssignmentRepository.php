<?php
declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use PDO;

final class AssignmentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param list<int> $excludedQueueIds
     * @return array<string,mixed>|null
     */
    public function nextPendingCaseForUpdate(?int $queueId = null, array $excludedQueueIds = []): ?array
    {
        $sql = "SELECT c.*
                FROM cases c
                JOIN work_queues q ON q.id=c.queue_id
                WHERE c.current_state='PENDING_ASSIGNMENT'
                  AND c.assigned_user_id IS NULL
                  AND q.is_active=1";

        $params = [];

        if ($queueId !== null) {
            $sql .= " AND c.queue_id=:qid";
            $params[':qid'] = $queueId;
        }

        $excludedQueueIds = array_values(array_unique(array_filter(
            array_map('intval', $excludedQueueIds),
            static fn (int $id): bool => $id > 0
        )));

        foreach ($excludedQueueIds as $index => $excludedId) {
            $placeholder = ':excluded_queue_' . $index;
            $sql .= " AND c.queue_id<>" . $placeholder;
            $params[$placeholder] = $excludedId;
        }

        $sql .= " ORDER BY q.priority ASC,c.created_at ASC,c.id ASC
                  LIMIT 1
                  FOR UPDATE";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function eligibleCandidateIds(int $queueId): array
    {
        $st = $this->pdo->prepare(
            "SELECT DISTINCT
                u.id,
                COALESCE(qa.capacity_override,q.default_capacity) capacity,
                u.last_assigned_at
             FROM queue_agents qa
             JOIN work_queues q
               ON q.id=qa.queue_id
              AND q.is_active=1
             JOIN users u
               ON u.id=qa.user_id
              AND u.is_active=1
              AND u.assign_enabled=1
             JOIN user_roles ur
               ON ur.user_id=u.id
             JOIN roles r
               ON r.id=ur.role_id
              AND r.code='AGENTE'
              AND r.is_active=1
             JOIN agent_presence ap
               ON ap.user_id=u.id
              AND ap.ended_at IS NULL
              AND ap.status_code='AVAILABLE'
              AND ap.last_heartbeat_at>=:presence_cutoff
             WHERE qa.queue_id=:qid
               AND qa.is_enabled=1
               AND qa.removed_at IS NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM queue_skills qs
                    WHERE qs.queue_id=qa.queue_id
                      AND qs.is_required=1
                      AND NOT EXISTS (
                          SELECT 1
                          FROM user_skills us
                          WHERE us.user_id=u.id
                            AND us.skill_id=qs.skill_id
                            AND us.is_active=1
                            AND us.removed_at IS NULL
                      )
               )
             ORDER BY
                CASE WHEN u.last_assigned_at IS NULL THEN 0 ELSE 1 END ASC,
                u.last_assigned_at ASC,
                qa.priority ASC,
                u.id ASC"
        );
        $st->execute([
            ':qid'=>$queueId,
            ':presence_cutoff'=>$this->presenceCutoff(),
        ]);

        return $st->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function lockUser(int $userId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT id,is_active,assign_enabled,last_assigned_at
             FROM users
             WHERE id=:uid
             FOR UPDATE"
        );
        $st->execute([':uid'=>$userId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function isAvailableNow(int $userId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1
             FROM agent_presence
             WHERE user_id=:uid
               AND ended_at IS NULL
               AND status_code='AVAILABLE'
               AND last_heartbeat_at>=:presence_cutoff
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->execute([
            ':uid'=>$userId,
            ':presence_cutoff'=>$this->presenceCutoff(),
        ]);

        return (bool)$st->fetchColumn();
    }

    public function isEligibleForQueue(int $queueId, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1
             FROM queue_agents qa
             JOIN users u
               ON u.id=qa.user_id
              AND u.is_active=1
              AND u.assign_enabled=1
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r
               ON r.id=ur.role_id
              AND r.code='AGENTE'
              AND r.is_active=1
             WHERE qa.queue_id=:qid
               AND qa.user_id=:uid
               AND qa.is_enabled=1
               AND qa.removed_at IS NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM queue_skills qs
                    WHERE qs.queue_id=qa.queue_id
                      AND qs.is_required=1
                      AND NOT EXISTS (
                          SELECT 1
                          FROM user_skills us
                          WHERE us.user_id=u.id
                            AND us.skill_id=qs.skill_id
                            AND us.is_active=1
                            AND us.removed_at IS NULL
                      )
               )
             LIMIT 1"
        );
        $st->execute([
            ':qid'=>$queueId,
            ':uid'=>$userId,
        ]);

        return (bool)$st->fetchColumn();
    }

    public function capacityForQueue(int $queueId, int $userId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(qa.capacity_override,q.default_capacity)
             FROM queue_agents qa
             JOIN work_queues q ON q.id=qa.queue_id
             WHERE qa.queue_id=:qid
               AND qa.user_id=:uid
               AND qa.is_enabled=1
               AND qa.removed_at IS NULL
             LIMIT 1"
        );
        $st->execute([
            ':qid'=>$queueId,
            ':uid'=>$userId,
        ]);

        $capacity = $st->fetchColumn();

        return $capacity === false ? 0 : (int)$capacity;
    }

    public function openCaseCount(int $queueId, int $userId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM cases
             WHERE queue_id=:qid
               AND assigned_user_id=:uid
               AND closed_at IS NULL
               AND current_state<>'PENDING_ASSIGNMENT'"
        );
        $st->execute([
            ':qid'=>$queueId,
            ':uid'=>$userId,
        ]);

        return (int)$st->fetchColumn();
    }

    public function assignCase(int $caseId, int $queueId, int $userId): void
    {
        $st = $this->pdo->prepare(
            "UPDATE cases
             SET assigned_user_id=:uid,
                 assigned_at=NOW(6),
                 current_state='ASSIGNED',
                 updated_at=NOW(6)
             WHERE id=:cid
               AND queue_id=:qid
               AND assigned_user_id IS NULL
               AND current_state='PENDING_ASSIGNMENT'"
        );
        $st->execute([
            ':uid'=>$userId,
            ':cid'=>$caseId,
            ':qid'=>$queueId,
        ]);

        if ($st->rowCount() !== 1) {
            throw new \RuntimeException('El caso cambió durante el reparto.');
        }

        $assignment = $this->pdo->prepare(
            "INSERT INTO case_assignments
             (case_id,queue_id,user_id,assignment_type,assigned_by,assigned_at)
             VALUES(:cid,:qid,:uid,'AUTO',NULL,NOW(6))"
        );
        $assignment->execute([
            ':cid'=>$caseId,
            ':qid'=>$queueId,
            ':uid'=>$userId,
        ]);

        $event = $this->pdo->prepare(
            "INSERT INTO case_events
             (case_id,actor_user_id,event_type,from_state,to_state,details_json,created_at)
             VALUES
             (:cid,NULL,'CASE_ASSIGNED','PENDING_ASSIGNMENT','ASSIGNED',:details,NOW(6))"
        );
        $event->execute([
            ':cid'=>$caseId,
            ':details'=>json_encode(
                [
                    'assignment_type'=>'AUTO',
                    'queue_id'=>$queueId,
                    'user_id'=>$userId,
                ],
                JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
            ),
        ]);

        $touch = $this->pdo->prepare(
            "UPDATE users
             SET last_assigned_at=NOW(6)
             WHERE id=:uid"
        );
        $touch->execute([':uid'=>$userId]);
    }

    private function presenceCutoff(): string
    {
        $stale = max(30, (int)($_ENV['AGENT_PRESENCE_STALE_SECONDS'] ?? 90));

        return (new DateTimeImmutable())
            ->modify("-{$stale} seconds")
            ->format('Y-m-d H:i:s.u');
    }
}
