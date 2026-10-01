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
                    )
               )
               AND (ass.valid_from IS NULL OR ass.valid_from<=:valid_from_date)
               AND (ass.valid_to IS NULL OR ass.valid_to>=:valid_to_date)
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