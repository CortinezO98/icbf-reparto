<?php
declare(strict_types=1);

namespace App\Services;

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

                // El fin de turno marca al agente como OFFLINE, pero no libera
                // los casos que ya está gestionando. La reasignación debe ser
                // explícita por parte de un supervisor o administrador.
                $this->repository->markAgentOfflineAtShiftEnd($userId, $now);
                $this->pdo->commit();

                $processed++;
                $queues[$queueId] = true;

                // Se registra la finalización del procesamiento sin casos
                // liberados. Los casos permanecen asignados a su agente.
                $this->pdo->beginTransaction();

                try {
                    $this->repository->finishShiftEndRun(
                        $scheduleId,
                        $date,
                        0,
                        []
                    );
                    $this->pdo->commit();
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    throw $e;
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
