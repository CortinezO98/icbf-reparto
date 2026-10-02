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

    /** @return list<int> */
    public function eligibleQueueIdsForAgent(int $userId): array
    {
        $tz = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'America/Bogota');
        $now = new \DateTimeImmutable('now', $tz);
        $date = $now->format('Y-m-d');
        $weekday = (int)$now->format('N');
        $time = $now->format('H:i:s');

        $st = $this->pdo->prepare(
            "SELECT DISTINCT q.id
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
             WHERE qa.user_id=:user_id
               AND qa.is_enabled=1
               AND qa.removed_at IS NULL
               AND EXISTS (
                    SELECT 1
                    FROM agent_shift_schedules ass
                    JOIN work_shifts ws
                      ON ws.id=ass.shift_id
                     AND ws.is_active=1
                    WHERE ass.user_id=u.id
                      AND ass.queue_id=qa.queue_id
                      AND ass.is_active=1
                      AND (
                           ass.schedule_date=:shift_date_specific
                           OR (
                               ass.schedule_date IS NULL
                               AND ass.weekday=:shift_weekday
                           )
                      )
                      AND (ass.valid_from IS NULL OR ass.valid_from<=:shift_valid_from)
                      AND (ass.valid_to IS NULL OR ass.valid_to>=:shift_valid_to)
                      AND ws.start_time<=:shift_time_start
                      AND ws.end_time>:shift_time_end
               )
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
             ORDER BY q.priority ASC,q.id ASC"
        );

        $st->execute([
            ':user_id'=>$userId,
            ':presence_cutoff'=>$this->presenceCutoff(),
            ':shift_date_specific'=>$date,
            ':shift_weekday'=>$weekday,
            ':shift_valid_from'=>$date,
            ':shift_valid_to'=>$date,
            ':shift_time_start'=>$time,
            ':shift_time_end'=>$time,
        ]);

        return array_map(
            'intval',
            $st->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    /** @return list<array<string,mixed>> */
    public function eligibleCandidateIds(int $queueId): array
    {
        $tz = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'America/Bogota');
        $now = new \DateTimeImmutable('now', $tz);
        $date = $now->format('Y-m-d');
        $weekday = (int)$now->format('N');
        $time = $now->format('H:i:s');

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
               AND EXISTS (
                    SELECT 1
                    FROM agent_shift_schedules ass
                    JOIN work_shifts ws
                      ON ws.id=ass.shift_id
                     AND ws.is_active=1
                    WHERE ass.user_id=u.id
                      AND ass.queue_id=qa.queue_id
                      AND ass.is_active=1
                      AND (
                           ass.schedule_date=:shift_date_specific
                           OR (
                               ass.schedule_date IS NULL
                               AND ass.weekday=:shift_weekday
                           )
                      )
                      AND (ass.valid_from IS NULL OR ass.valid_from<=:shift_valid_from)
                      AND (ass.valid_to IS NULL OR ass.valid_to>=:shift_valid_to)
                      AND ws.start_time<=:shift_time_start
                      AND ws.end_time>:shift_time_end
               )
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
            ':shift_date_specific'=>$date,
            ':shift_weekday'=>$weekday,
            ':shift_valid_from'=>$date,
            ':shift_valid_to'=>$date,
            ':shift_time_start'=>$time,
            ':shift_time_end'=>$time,
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

    public function isOnShiftForQueue(
        int $queueId,
        int $userId,
        ?\DateTimeImmutable $now = null
    ): bool {
        $tz = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'America/Bogota');
        $local = ($now ?? new \DateTimeImmutable('now', $tz))->setTimezone($tz);
        $date = $local->format('Y-m-d');
        $weekday = (int)$local->format('N');
        $time = $local->format('H:i:s');

        $st = $this->pdo->prepare(
            "SELECT 1
             FROM agent_shift_schedules ass
             JOIN work_shifts ws
               ON ws.id=ass.shift_id
              AND ws.is_active=1
             WHERE ass.user_id=:user_id
               AND ass.queue_id=:queue_id
               AND ass.is_active=1
               AND (
                    ass.schedule_date=:specific_date
                    OR (
                        ass.schedule_date IS NULL
                        AND ass.weekday=:weekday
                    )
               )
               AND (ass.valid_from IS NULL OR ass.valid_from<=:valid_from)
               AND (ass.valid_to IS NULL OR ass.valid_to>=:valid_to)
               AND ws.start_time<=:start_time
               AND ws.end_time>:end_time
             ORDER BY
                CASE WHEN ass.schedule_date IS NULL THEN 1 ELSE 0 END,
                ass.valid_from DESC,
                ws.start_time DESC,
                ass.id DESC
             LIMIT 1"
        );
        $st->execute([
            ':user_id'=>$userId,
            ':queue_id'=>$queueId,
            ':specific_date'=>$date,
            ':weekday'=>$weekday,
            ':valid_from'=>$date,
            ':valid_to'=>$date,
            ':start_time'=>$time,
            ':end_time'=>$time,
        ]);

        return (bool)$st->fetchColumn();
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

    public function assignCase(
        int $caseId,
        int $queueId,
        int $userId,
        string $assignmentType = 'AUTO',
        ?int $assignedBy = null
    ): void
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

        $assignmentType = strtoupper(trim($assignmentType));

        if (!in_array($assignmentType, ['AUTO','REASSIGN'], true)) {
            throw new \InvalidArgumentException('Tipo de asignación no permitido.');
        }

        $assignment = $this->pdo->prepare(
            "INSERT INTO case_assignments
             (case_id,queue_id,user_id,assignment_type,assigned_by,assigned_at)
             VALUES(:cid,:qid,:uid,:assignment_type,:assigned_by,NOW(6))"
        );
        $assignment->execute([
            ':cid'=>$caseId,
            ':qid'=>$queueId,
            ':uid'=>$userId,
            ':assignment_type'=>$assignmentType,
            ':assigned_by'=>$assignedBy,
        ]);

        $event = $this->pdo->prepare(
            "INSERT INTO case_events
             (case_id,actor_user_id,event_type,from_state,to_state,details_json,created_at)
             VALUES
             (:cid,:actor,'CASE_ASSIGNED','PENDING_ASSIGNMENT','ASSIGNED',:details,NOW(6))"
        );
        $event->execute([
            ':cid'=>$caseId,
            ':actor'=>$assignedBy,
            ':details'=>json_encode(
                [
                    'assignment_type'=>$assignmentType,
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

    /**
     * @return list<array{id:int,full_name:string,username:string,capacity:int,open_cases:int,free_capacity:int}>
     */
    public function reassignmentCandidates(
        int $caseId,
        int $currentUserId
    ): array {
        $st = $this->pdo->prepare(
            "SELECT c.queue_id
             FROM cases c
             WHERE c.id=:case_id
               AND c.closed_at IS NULL
             LIMIT 1"
        );
        $st->execute([':case_id'=>$caseId]);
        $case = $st->fetch();

        if (!$case || $case['queue_id'] === null) {
            return [];
        }

        $queueId = (int)$case['queue_id'];
        $candidateRows = $this->eligibleCandidateIds($queueId);
        $candidateIds = [];

        foreach ($candidateRows as $candidate) {
            $candidateIds[] = (int)$candidate['id'];
        }

        $candidateIds = array_values(array_unique(array_filter(
            $candidateIds,
            static fn (int $id): bool => $id !== $currentUserId
        )));

        if ($candidateIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($candidateIds as $index => $candidateId) {
            $placeholder = ':candidate_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $candidateId;
        }

        $st = $this->pdo->prepare(
            "SELECT id,full_name,username
             FROM users
             WHERE id IN (" . implode(',', $placeholders) . ")
             ORDER BY full_name,id"
        );
        $st->execute($params);
        $users = $st->fetchAll() ?: [];

        $result = [];

        foreach ($users as $user) {
            $userId = (int)$user['id'];
            $capacity = $this->capacityForQueue($queueId, $userId);
            $openCases = $this->openCaseCount($queueId, $userId);

            $result[] = [
                'id'=>$userId,
                'full_name'=>(string)$user['full_name'],
                'username'=>(string)$user['username'],
                'capacity'=>$capacity,
                'open_cases'=>$openCases,
                'free_capacity'=>max(0, $capacity - $openCases),
            ];
        }

        return $result;
    }

    /**
     * Reassign an open case to an eligible agent.
     *
     * @return array{from_user_id:int,to_user_id:int,queue_id:int}
     */
    public function reassignCase(
        int $caseId,
        int $newUserId,
        int $actorUserId,
        string $reason
    ): array {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('La razón de reasignación es obligatoria.');
        }

        $this->pdo->beginTransaction();

        try {
            $lock = $this->pdo->prepare(
                "SELECT id,queue_id,assigned_user_id,current_state,closed_at
                 FROM cases
                 WHERE id=:case_id
                 FOR UPDATE"
            );
            $lock->execute([':case_id'=>$caseId]);
            $case = $lock->fetch();

            if (!$case) {
                throw new \RuntimeException('Caso no encontrado.');
            }

            if ($case['closed_at'] !== null || (string)$case['current_state'] === 'CLOSED') {
                throw new \RuntimeException('El caso ya está cerrado.');
            }

            $currentUserId = (int)($case['assigned_user_id'] ?? 0);
            $queueId = (int)($case['queue_id'] ?? 0);

            if ($currentUserId <= 0 || $queueId <= 0) {
                throw new \RuntimeException('El caso no tiene una asignación activa válida.');
            }

            if ($currentUserId === $newUserId) {
                throw new \InvalidArgumentException('El nuevo agente debe ser diferente al actual.');
            }

            $newUser = $this->lockUser($newUserId);

            if (
                !$newUser
                || (int)$newUser['is_active'] !== 1
                || (int)$newUser['assign_enabled'] !== 1
            ) {
                throw new \RuntimeException('El agente destino no está habilitado para recibir casos.');
            }

            if (!$this->isAvailableNow($newUserId)) {
                throw new \RuntimeException('El agente destino no está disponible o su señal está vencida.');
            }

            if (!$this->isOnShiftForQueue($queueId, $newUserId)) {
                throw new \RuntimeException('El agente destino no está dentro de un turno vigente para esta cola.');
            }

            if (!$this->isEligibleForQueue($queueId, $newUserId)) {
                throw new \RuntimeException('El agente destino no cumple la configuración de la cola o sus habilidades.');
            }

            $capacity = $this->capacityForQueue($queueId, $newUserId);
            $openCases = $this->openCaseCount($queueId, $newUserId);

            if ($capacity <= 0 || $openCases >= $capacity) {
                throw new \RuntimeException('El agente destino no tiene capacidad disponible.');
            }

            $closeAssignment = $this->pdo->prepare(
                "UPDATE case_assignments
                 SET ended_at=NOW(6),
                     end_reason='MANUAL_REASSIGN'
                 WHERE case_id=:case_id
                   AND user_id=:current_user_id
                   AND ended_at IS NULL"
            );
            $closeAssignment->execute([
                ':case_id'=>$caseId,
                ':current_user_id'=>$currentUserId,
            ]);

            if ($closeAssignment->rowCount() !== 1) {
                throw new \RuntimeException('No se encontró la asignación activa del caso.');
            }

            $update = $this->pdo->prepare(
                "UPDATE cases
                 SET assigned_user_id=:new_user_id,
                     assigned_at=NOW(6),
                     current_state='ASSIGNED',
                     updated_at=NOW(6)
                 WHERE id=:case_id
                   AND assigned_user_id=:current_user_id
                   AND current_state='ASSIGNED'
                   AND closed_at IS NULL"
            );
            $update->execute([
                ':case_id'=>$caseId,
                ':new_user_id'=>$newUserId,
                ':current_user_id'=>$currentUserId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('El caso cambió durante la reasignación.');
            }

            $assignment = $this->pdo->prepare(
                "INSERT INTO case_assignments
                 (case_id,queue_id,user_id,assignment_type,assigned_by,assigned_at)
                 VALUES(:case_id,:queue_id,:user_id,'REASSIGN',:assigned_by,NOW(6))"
            );
            $assignment->execute([
                ':case_id'=>$caseId,
                ':queue_id'=>$queueId,
                ':user_id'=>$newUserId,
                ':assigned_by'=>$actorUserId,
            ]);

            $event = $this->pdo->prepare(
                "INSERT INTO case_events
                 (case_id,actor_user_id,event_type,from_state,to_state,details_json,created_at)
                 VALUES
                 (:case_id,:actor,'CASE_REASSIGNED','ASSIGNED','ASSIGNED',:details,NOW(6))"
            );
            $event->execute([
                ':case_id'=>$caseId,
                ':actor'=>$actorUserId,
                ':details'=>json_encode(
                    [
                        'reason'=>$reason,
                        'queue_id'=>$queueId,
                        'from_user_id'=>$currentUserId,
                        'to_user_id'=>$newUserId,
                        'assignment_type'=>'REASSIGN',
                    ],
                    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
                ),
            ]);

            $touch = $this->pdo->prepare(
                "UPDATE users
                 SET last_assigned_at=NOW(6)
                 WHERE id=:user_id"
            );
            $touch->execute([':user_id'=>$newUserId]);

            $this->pdo->commit();

            return [
                'from_user_id'=>$currentUserId,
                'to_user_id'=>$newUserId,
                'queue_id'=>$queueId,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }


    /**
     * Recupera casos que fueron asignados previamente al agente y que quedaron
     * pendientes por una liberación automática ocurrida antes de la regla de
     * permanencia. Solo recupera casos cuya última asignación histórica fue
     * precisamente este agente y cuya liberación tuvo un motivo automático.
     *
     * @return list<int>
     */
    public function recoverInterruptedCasesForAgent(
        int $queueId,
        int $userId,
        int $limit
    ): array {
        $limit = max(0, min(500, $limit));

        if ($limit === 0) {
            return [];
        }

        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $st = $this->pdo->prepare(
                "SELECT
                    c.id,
                    c.case_number,
                    c.queue_id
                 FROM cases c
                 JOIN case_assignments ca
                   ON ca.id=(
                        SELECT ca2.id
                        FROM case_assignments ca2
                        WHERE ca2.case_id=c.id
                        ORDER BY ca2.assigned_at DESC,ca2.id DESC
                        LIMIT 1
                   )
                 WHERE c.queue_id=:queue_id
                   AND c.assigned_user_id IS NULL
                   AND c.closed_at IS NULL
                   AND c.current_state='PENDING_ASSIGNMENT'
                   AND ca.user_id=:user_id
                   AND ca.ended_at IS NOT NULL
                   AND ca.end_reason IN (
                       'STALE_HEARTBEAT',
                       'LOGOUT',
                       'OFFLINE',
                       'SHIFT_END'
                   )
                 ORDER BY ca.assigned_at ASC,c.id ASC
                 LIMIT {$limit}
                 FOR UPDATE"
            );
            $st->execute([
                ':queue_id'=>$queueId,
                ':user_id'=>$userId,
            ]);
            $cases = $st->fetchAll() ?: [];

            if ($cases === []) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return [];
            }

            $caseIds = [];

            $update = $this->pdo->prepare(
                "UPDATE cases
                 SET assigned_user_id=:user_id,
                     assigned_at=NOW(6),
                     current_state='ASSIGNED',
                     updated_at=NOW(6)
                 WHERE id=:case_id
                   AND assigned_user_id IS NULL
                   AND current_state='PENDING_ASSIGNMENT'
                   AND closed_at IS NULL"
            );

            $assignment = $this->pdo->prepare(
                "INSERT INTO case_assignments
                 (case_id,queue_id,user_id,assignment_type,assigned_by,assigned_at)
                 VALUES
                 (:case_id,:queue_id,:user_id,'AUTO',NULL,NOW(6))"
            );

            $event = $this->pdo->prepare(
                "INSERT INTO case_events
                 (case_id,actor_user_id,event_type,from_state,to_state,details_json,created_at)
                 VALUES
                 (:case_id,NULL,'CASE_RECOVERED','PENDING_ASSIGNMENT','ASSIGNED',:details,NOW(6))"
            );

            foreach ($cases as $case) {
                $caseId = (int)$case['id'];

                $update->execute([
                    ':case_id'=>$caseId,
                    ':user_id'=>$userId,
                ]);

                if ($update->rowCount() !== 1) {
                    throw new \RuntimeException(
                        "No fue posible recuperar el caso {$caseId}."
                    );
                }

                $assignment->execute([
                    ':case_id'=>$caseId,
                    ':queue_id'=>(int)$case['queue_id'],
                    ':user_id'=>$userId,
                ]);

                $event->execute([
                    ':case_id'=>$caseId,
                    ':details'=>json_encode(
                        [
                            'reason'=>'RECOVER_PREVIOUS_AGENT_ASSIGNMENT',
                            'user_id'=>$userId,
                            'queue_id'=>(int)$case['queue_id'],
                            'case_number'=>(string)$case['case_number'],
                        ],
                        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
                    ),
                ]);

                $caseIds[] = $caseId;
            }

            if ($caseIds !== []) {
                $touch = $this->pdo->prepare(
                    "UPDATE users
                     SET last_assigned_at=NOW(6)
                     WHERE id=:user_id"
                );
                $touch->execute([':user_id'=>$userId]);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $caseIds;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }


    /**
     * Libera explícitamente los casos abiertos de un agente.
     *
     * No debe invocarse como consecuencia automática de logout, pérdida de
     * heartbeat o desconexión. La permanencia del caso con su agente es el
     * comportamiento normal hasta una reasignación explícita.
     *
     * @return array{case_ids:list<int>,queue_ids:list<int>}
     */
    public function releaseCasesForAgent(
        int $userId,
        string $reason = 'STALE_HEARTBEAT'
    ): array {
        $reason = strtoupper(trim($reason));

        if (!in_array($reason, ['STALE_HEARTBEAT', 'LOGOUT', 'OFFLINE'], true)) {
            throw new \InvalidArgumentException('Motivo de liberación no permitido.');
        }

        $this->pdo->beginTransaction();

        try {
            $st = $this->pdo->prepare(
                "SELECT
                    c.id,
                    c.case_number,
                    c.queue_id
                 FROM cases c
                 WHERE c.assigned_user_id=:user_id
                   AND c.closed_at IS NULL
                   AND c.current_state='ASSIGNED'
                 ORDER BY COALESCE(c.radicated_at,c.created_at),c.id
                 FOR UPDATE"
            );
            $st->execute([':user_id'=>$userId]);
            $cases = $st->fetchAll() ?: [];

            $caseIds = [];
            $queueIds = [];

            foreach ($cases as $case) {
                $caseId = (int)$case['id'];
                $queueId = (int)$case['queue_id'];

                $closeAssignment = $this->pdo->prepare(
                    "UPDATE case_assignments
                     SET ended_at=NOW(6),
                         end_reason=:reason
                     WHERE case_id=:case_id
                       AND user_id=:user_id
                       AND ended_at IS NULL"
                );
                $closeAssignment->execute([
                    ':reason'=>$reason,
                    ':case_id'=>$caseId,
                    ':user_id'=>$userId,
                ]);

                $update = $this->pdo->prepare(
                    "UPDATE cases
                     SET assigned_user_id=NULL,
                         assigned_at=NULL,
                         current_state='PENDING_ASSIGNMENT',
                         updated_at=NOW(6)
                     WHERE id=:case_id
                       AND assigned_user_id=:user_id
                       AND closed_at IS NULL"
                );
                $update->execute([
                    ':case_id'=>$caseId,
                    ':user_id'=>$userId,
                ]);

                if ($update->rowCount() !== 1) {
                    throw new \RuntimeException(
                        "No fue posible liberar el caso {$caseId}."
                    );
                }

                $event = $this->pdo->prepare(
                    "INSERT INTO case_events
                     (case_id,actor_user_id,event_type,from_state,to_state,details_json,created_at)
                     VALUES
                     (:case_id,NULL,'CASE_RELEASED_OFFLINE','ASSIGNED','PENDING_ASSIGNMENT',:details,NOW(6))"
                );
                $event->execute([
                    ':case_id'=>$caseId,
                    ':details'=>json_encode(
                        [
                            'reason'=>$reason,
                            'previous_user_id'=>$userId,
                            'queue_id'=>$queueId,
                            'case_number'=>(string)$case['case_number'],
                        ],
                        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
                    ),
                ]);

                $caseIds[] = $caseId;

                if ($queueId > 0) {
                    $queueIds[$queueId] = true;
                }
            }

            $this->pdo->commit();

            return [
                'case_ids'=>$caseIds,
                'queue_ids'=>array_map('intval', array_keys($queueIds)),
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function presenceCutoff(): string
    {
        $stale = max(30, (int)($_ENV['AGENT_PRESENCE_STALE_SECONDS'] ?? 90));

        return (new DateTimeImmutable())
            ->modify("-{$stale} seconds")
            ->format('Y-m-d H:i:s.u');
    }
}