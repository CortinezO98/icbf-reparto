<?php
declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class ShiftRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function allShifts(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE is_active=1' : '';

        return $this->pdo->query(
            "SELECT id,code,name,start_time,end_time,is_active
             FROM work_shifts
             {$where}
             ORDER BY start_time,end_time,name,id"
        )->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function allAgents(): array
    {
        return $this->pdo->query(
            "SELECT DISTINCT
                u.id,
                u.full_name,
                u.username
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r
               ON r.id=ur.role_id
              AND r.code='AGENTE'
              AND r.is_active=1
             WHERE u.is_active=1
             ORDER BY u.full_name,u.id"
        )->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function allQueues(): array
    {
        return $this->pdo->query(
            "SELECT id,code,name
             FROM work_queues
             WHERE is_active=1
             ORDER BY priority,code"
        )->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function schedules(): array
    {
        return $this->pdo->query(
            "SELECT
                ass.id,
                ass.user_id,
                ass.queue_id,
                ass.shift_id,
                ass.schedule_date,
                ass.weekday,
                ass.valid_from,
                ass.valid_to,
                ass.is_active,
                u.full_name agent_name,
                u.username agent_username,
                q.code queue_code,
                q.name queue_name,
                ws.code shift_code,
                ws.name shift_name,
                ws.start_time,
                ws.end_time
             FROM agent_shift_schedules ass
             JOIN users u ON u.id=ass.user_id
             JOIN work_queues q ON q.id=ass.queue_id
             JOIN work_shifts ws ON ws.id=ass.shift_id
             ORDER BY
                ass.is_active DESC,
                COALESCE(ass.schedule_date,'9999-12-31'),
                COALESCE(ass.weekday,0),
                ws.start_time,
                u.full_name,
                ass.id DESC"
        )->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function findSchedule(int $scheduleId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT *
             FROM agent_shift_schedules
             WHERE id=:id
             LIMIT 1"
        );
        $st->execute([':id'=>$scheduleId]);

        $row = $st->fetch();

        return $row ?: null;
    }

    public function createShift(
        string $code,
        string $name,
        string $startTime,
        string $endTime,
        int $createdBy
    ): int {
        $code = strtoupper(trim($code));
        $name = trim($name);

        if (!preg_match('/^[A-Z0-9_]{2,100}$/', $code)) {
            throw new \InvalidArgumentException('El código del turno no es válido.');
        }

        if ($name === '') {
            throw new \InvalidArgumentException('El nombre del turno es obligatorio.');
        }

        if (!$this->validTime($startTime) || !$this->validTime($endTime)) {
            throw new \InvalidArgumentException('La hora de inicio o fin no es válida.');
        }

        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException(
                'El turno debe terminar después de la hora de inicio.'
            );
        }

        $st = $this->pdo->prepare(
            "INSERT INTO work_shifts
             (code,name,start_time,end_time,created_by)
             VALUES(:code,:name,:start_time,:end_time,:created_by)"
        );
        $st->execute([
            ':code'=>$code,
            ':name'=>$name,
            ':start_time'=>$startTime,
            ':end_time'=>$endTime,
            ':created_by'=>$createdBy,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function createSchedule(
        int $userId,
        int $queueId,
        int $shiftId,
        ?string $scheduleDate,
        ?int $weekday,
        ?string $validFrom,
        ?string $validTo,
        int $assignedBy
    ): int {
        $scheduleDate = $this->normalizeDate($scheduleDate);
        $validFrom = $this->normalizeDate($validFrom);
        $validTo = $this->normalizeDate($validTo);

        if ($scheduleDate !== null) {
            $weekday = null;
        } else {
            if ($weekday === null || $weekday < 1 || $weekday > 7) {
                throw new \InvalidArgumentException('Selecciona un día de semana válido.');
            }
        }

        if (
            $validFrom !== null
            && $validTo !== null
            && $validTo < $validFrom
        ) {
            throw new \InvalidArgumentException(
                'La fecha final no puede ser anterior a la fecha inicial.'
            );
        }

        $this->assertAgent($userId);
        $this->assertActiveQueue($queueId);
        $this->assertActiveShift($shiftId);

        $shift = $this->shiftById($shiftId);

        if ($shift === null) {
            throw new \InvalidArgumentException('El turno seleccionado no existe.');
        }

        $this->assertNoOverlap(
            $userId,
            $queueId,
            $scheduleDate,
            $weekday,
            $validFrom,
            $validTo,
            (string)$shift['start_time'],
            (string)$shift['end_time']
        );

        $st = $this->pdo->prepare(
            "INSERT INTO agent_shift_schedules
             (
                user_id,queue_id,shift_id,schedule_date,weekday,
                valid_from,valid_to,is_active,assigned_by
             )
             VALUES
             (
                :user_id,:queue_id,:shift_id,:schedule_date,:weekday,
                :valid_from,:valid_to,1,:assigned_by
             )"
        );
        $st->execute([
            ':user_id'=>$userId,
            ':queue_id'=>$queueId,
            ':shift_id'=>$shiftId,
            ':schedule_date'=>$scheduleDate,
            ':weekday'=>$weekday,
            ':valid_from'=>$validFrom,
            ':valid_to'=>$validTo,
            ':assigned_by'=>$assignedBy,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function toggleSchedule(int $scheduleId): void
    {
        $st = $this->pdo->prepare(
            "UPDATE agent_shift_schedules
             SET is_active=1-is_active,
                 updated_at=NOW(6)
             WHERE id=:id"
        );
        $st->execute([':id'=>$scheduleId]);

        if ($st->rowCount() !== 1) {
            throw new \RuntimeException('El cronograma no existe.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function endedSchedules(DateTimeImmutable $now): array
    {
        $tz = new DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'America/Bogota');
        $local = $now->setTimezone($tz);
        $date = $local->format('Y-m-d');
        $weekday = (int)$local->format('N');
        $time = $local->format('H:i:s');

        $st = $this->pdo->prepare(
            "SELECT
                ass.id,
                ass.user_id,
                ass.queue_id,
                ass.shift_id,
                ass.schedule_date,
                ass.weekday,
                ws.start_time,
                ws.end_time,
                u.full_name agent_name
             FROM agent_shift_schedules ass
             JOIN work_shifts ws
               ON ws.id=ass.shift_id
              AND ws.is_active=1
             JOIN users u
               ON u.id=ass.user_id
             JOIN work_queues q
               ON q.id=ass.queue_id
              AND q.is_active=1
             LEFT JOIN agent_shift_runs asr
               ON asr.schedule_id=ass.id
              AND asr.shift_date=:run_date
              AND asr.event_type='SHIFT_END'
             WHERE ass.is_active=1
               AND asr.id IS NULL
               AND (
                    ass.schedule_date=:schedule_date
                    OR (
                        ass.schedule_date IS NULL
                        AND ass.weekday=:weekday
                        AND (ass.valid_from IS NULL OR ass.valid_from<=:valid_from_date)
                        AND (ass.valid_to IS NULL OR ass.valid_to>=:valid_to_date)
                    )
               )
               AND ws.end_time<=:current_time
               AND ws.end_time>ws.start_time
             ORDER BY ws.end_time,ass.id"
        );
        $st->execute([
            ':run_date'=>$date,
            ':schedule_date'=>$date,
            ':weekday'=>$weekday,
            ':valid_from_date'=>$date,
            ':valid_to_date'=>$date,
            ':current_time'=>$time,
        ]);

        return $st->fetchAll() ?: [];
    }

    public function beginShiftEndRun(
        int $scheduleId,
        int $userId,
        int $queueId,
        string $date
    ): bool {
        $st = $this->pdo->prepare(
            "INSERT IGNORE INTO agent_shift_runs
             (schedule_id,user_id,queue_id,shift_date,event_type)
             VALUES(:schedule_id,:user_id,:queue_id,:shift_date,'SHIFT_END')"
        );
        $st->execute([
            ':schedule_id'=>$scheduleId,
            ':user_id'=>$userId,
            ':queue_id'=>$queueId,
            ':shift_date'=>$date,
        ]);

        return $st->rowCount() === 1;
    }

    public function finishShiftEndRun(
        int $scheduleId,
        string $date,
        int $releasedCases,
        int $pendingCases
    ): void {
        $st = $this->pdo->prepare(
            "UPDATE agent_shift_runs
             SET processed_at=NOW(6),
                 released_cases=:released_cases,
                 pending_cases=:pending_cases
             WHERE schedule_id=:schedule_id
               AND shift_date=:shift_date
               AND event_type='SHIFT_END'"
        );
        $st->execute([
            ':released_cases'=>$releasedCases,
            ':pending_cases'=>$pendingCases,
            ':schedule_id'=>$scheduleId,
            ':shift_date'=>$date,
        ]);
    }

    public function releaseCasesForShiftEnd(
        int $userId,
        int $queueId
    ): int {
        $st = $this->pdo->prepare(
            "SELECT id,case_number,current_state
             FROM cases
             WHERE assigned_user_id=:user_id
               AND queue_id=:queue_id
               AND closed_at IS NULL
               AND current_state<>'CLOSED'
             ORDER BY COALESCE(radicated_at,created_at),id
             FOR UPDATE"
        );
        $st->execute([
            ':user_id'=>$userId,
            ':queue_id'=>$queueId,
        ]);
        $cases = $st->fetchAll() ?: [];

        foreach ($cases as $case) {
            $caseId = (int)$case['id'];

            $this->pdo->prepare(
                "UPDATE case_assignments
                 SET ended_at=NOW(6),
                     end_reason='SHIFT_END'
                 WHERE case_id=:case_id
                   AND user_id=:user_id
                   AND ended_at IS NULL"
            )->execute([
                ':case_id'=>$caseId,
                ':user_id'=>$userId,
            ]);

            $this->pdo->prepare(
                "UPDATE cases
                 SET assigned_user_id=NULL,
                     assigned_at=NULL,
                     current_state='PENDING_ASSIGNMENT',
                     updated_at=NOW(6)
                 WHERE id=:case_id
                   AND assigned_user_id=:user_id"
            )->execute([
                ':case_id'=>$caseId,
                ':user_id'=>$userId,
            ]);

            $this->pdo->prepare(
                "INSERT INTO case_events
                 (case_id,actor_user_id,event_type,from_state,to_state,details_json,created_at)
                 VALUES
                 (:case_id,NULL,'CASE_RELEASED_SHIFT_END','ASSIGNED','PENDING_ASSIGNMENT',:details,NOW(6))"
            )->execute([
                ':case_id'=>$caseId,
                ':details'=>json_encode(
                    [
                        'reason'=>'SHIFT_END',
                        'previous_user_id'=>$userId,
                        'queue_id'=>$queueId,
                        'case_number'=>(string)$case['case_number'],
                    ],
                    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
                ),
            ]);
        }

        return count($cases);
    }

    public function markAgentOfflineAtShiftEnd(int $userId): void
    {
        $st = $this->pdo->prepare(
            "SELECT id
             FROM agent_presence
             WHERE user_id=:user_id
               AND ended_at IS NULL
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $st->execute([':user_id'=>$userId]);
        $presenceId = $st->fetchColumn();

        if ($presenceId === false) {
            return;
        }

        $this->pdo->prepare(
            "UPDATE agent_presence
             SET ended_at=NOW(6),
                 last_heartbeat_at=NOW(6)
             WHERE id=:id"
        )->execute([':id'=>(int)$presenceId]);

        $this->pdo->prepare(
            "INSERT INTO agent_presence
             (user_id,status_code,started_at,last_heartbeat_at,notes,source,set_by)
             VALUES
             (:user_id,'OFFLINE',NOW(6),NOW(6),'SHIFT_END','SYSTEM',NULL)"
        )->execute([':user_id'=>$userId]);
    }

    private function assertAgent(int $userId): void
    {
        $st = $this->pdo->prepare(
            "SELECT 1
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r
               ON r.id=ur.role_id
              AND r.code='AGENTE'
              AND r.is_active=1
             WHERE u.id=:user_id
               AND u.is_active=1
             LIMIT 1"
        );
        $st->execute([':user_id'=>$userId]);

        if (!$st->fetchColumn()) {
            throw new \InvalidArgumentException(
                'El usuario seleccionado no es un agente activo.'
            );
        }
    }

    private function assertActiveQueue(int $queueId): void
    {
        $st = $this->pdo->prepare(
            "SELECT 1 FROM work_queues WHERE id=:id AND is_active=1 LIMIT 1"
        );
        $st->execute([':id'=>$queueId]);

        if (!$st->fetchColumn()) {
            throw new \InvalidArgumentException('La cola seleccionada no está activa.');
        }
    }

    private function assertActiveShift(int $shiftId): void
    {
        $st = $this->pdo->prepare(
            "SELECT 1 FROM work_shifts WHERE id=:id AND is_active=1 LIMIT 1"
        );
        $st->execute([':id'=>$shiftId]);

        if (!$st->fetchColumn()) {
            throw new \InvalidArgumentException('El turno seleccionado no está activo.');
        }
    }

    /** @return array<string,mixed>|null */
    private function shiftById(int $shiftId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT id,code,name,start_time,end_time
             FROM work_shifts
             WHERE id=:id
             LIMIT 1"
        );
        $st->execute([':id'=>$shiftId]);

        $row = $st->fetch();

        return $row ?: null;
    }

    private function assertNoOverlap(
        int $userId,
        int $queueId,
        ?string $scheduleDate,
        ?int $weekday,
        ?string $validFrom,
        ?string $validTo,
        string $startTime,
        string $endTime
    ): void {
        if ($scheduleDate !== null) {
            $st = $this->pdo->prepare(
                "SELECT ws.start_time,ws.end_time
                 FROM agent_shift_schedules ass
                 JOIN work_shifts ws ON ws.id=ass.shift_id
                 WHERE ass.user_id=:user_id
                   AND ass.queue_id=:queue_id
                   AND ass.is_active=1
                   AND ass.schedule_date=:schedule_date
                 LIMIT 100"
            );
            $st->execute([
                ':user_id'=>$userId,
                ':queue_id'=>$queueId,
                ':schedule_date'=>$scheduleDate,
            ]);
        } else {
            $st = $this->pdo->prepare(
                "SELECT ws.start_time,ws.end_time
                 FROM agent_shift_schedules ass
                 JOIN work_shifts ws ON ws.id=ass.shift_id
                 WHERE ass.user_id=:user_id
                   AND ass.queue_id=:queue_id
                   AND ass.is_active=1
                   AND ass.schedule_date IS NULL
                   AND ass.weekday=:weekday
                   AND (ass.valid_from IS NULL OR :valid_to IS NULL OR ass.valid_from<=:valid_to)
                   AND (ass.valid_to IS NULL OR :valid_from IS NULL OR ass.valid_to>=:valid_from)
                 LIMIT 100"
            );
            $st->execute([
                ':user_id'=>$userId,
                ':queue_id'=>$queueId,
                ':weekday'=>$weekday,
                ':valid_from'=>$validFrom,
                ':valid_to'=>$validTo,
            ]);
        }

        foreach ($st->fetchAll() ?: [] as $existing) {
            if (
                (string)$existing['start_time'] < $endTime
                && (string)$existing['end_time'] > $startTime
            ) {
                throw new \InvalidArgumentException(
                    'El agente ya tiene un turno que se cruza en ese período.'
                );
            }
        }
    }

    private function validTime(string $time): bool
    {
        return (bool)preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
            $time
        );
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Una de las fechas no es válida.');
        }

        return $value;
    }
}