<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AssignmentRepository;
use App\Repositories\ShiftRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class ShiftService
{
    public function __construct(
        private PDO $pdo,
        private ShiftRepository $repository
    ) {
    }

    /** @return array{processed:int,released:int,queues:list<int>} */
    public function processEndedShifts(
        ?DateTimeImmutable $now = null
    ): array {
        $tz = new DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'America/Bogota');
        $now = ($now ?? new DateTimeImmutable('now', $tz))->setTimezone($tz);

        $processed = 0;
        $released = 0;
        $queues = [];

        foreach ($this->repository->endedSchedules($now) as $schedule) {
            $scheduleId = (int)$schedule['id'];
            $userId = (int)$schedule['user_id'];
            $queueId = (int)$schedule['queue_id'];
            $date = $now->format('Y-m-d');

            $this->pdo->beginTransaction();

            try {
                if (!$this->repository->beginShiftEndRun(
                    $scheduleId,
                    $userId,
                    $queueId,
                    $date
                )) {
                    $this->pdo->commit();
                    continue;
                }

                $this->repository->markAgentOfflineAtShiftEnd($userId, $now);

                $releasedCaseIds = $this->repository->releaseCasesForShiftEnd(
                    $userId,
                    $queueId
                );

                $this->pdo->commit();

                $processed++;
                $released += count($releasedCaseIds);
                $queues[$queueId] = true;

                $assignmentError = null;

                try {
                    $engine = new \App\Services\Assignment\AssignmentEngine(
                        $this->pdo,
                        new AssignmentRepository($this->pdo)
                    );
                    $engine->run($queueId, 500, 'REASSIGN');
                } catch (\Throwable $e) {
                    $assignmentError = $e;
                    error_log(
                        '[SHIFT_END_REASSIGN] ' . $e->getMessage()
                    );
                }

                $pendingCases = $this->repository->pendingForCaseIds(
                    $releasedCaseIds
                );

                $this->pdo->beginTransaction();
                try {
                    $this->repository->finishShiftEndRun(
                        $scheduleId,
                        $date,
                        count($releasedCaseIds),
                        $pendingCases
                    );
                    $this->pdo->commit();
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    throw $e;
                }

                if ($assignmentError !== null) {
                    throw $assignmentError;
                }
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $e;
            }
        }

        return [
            'processed'=>$processed,
            'released'=>$released,
            'queues'=>array_map('intval', array_keys($queues)),
        ];
    }

    /** @return array<string,mixed>|null */
    public function currentShiftForAgentQueue(
        int $userId,
        int $queueId,
        ?DateTimeImmutable $now = null
    ): ?array {
        return $this->repository->currentShiftForAgentQueue(
            $userId,
            $queueId,
            $now
        );
    }
}