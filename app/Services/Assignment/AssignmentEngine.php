<?php
declare(strict_types=1);

namespace App\Services\Assignment;

use App\Repositories\AssignmentRepository;
use PDO;

final class AssignmentEngine
{
    public function __construct(
        private PDO $pdo,
        private AssignmentRepository $repo
    ) {
    }

    /** @return array{assigned:int,no_agent:int,iterations:int} */
    public function run(
        ?int $queueId = null,
        int $maxAssignments = 500,
        string $assignmentType = 'AUTO'
    ): array
    {
        $assignmentType = strtoupper(trim($assignmentType));

        if (!in_array($assignmentType, ['AUTO','REASSIGN'], true)) {
            throw new \InvalidArgumentException('Tipo de asignación no permitido.');
        }

        $maxAssignments = max(1, min(5000, $maxAssignments));

        $assigned = 0;
        $noAgent = 0;
        $iterations = 0;
        $blockedQueueIds = [];

        while ($assigned < $maxAssignments) {
            $iterations++;

            $result = $this->assignOne(
                $queueId,
                $blockedQueueIds,
                $assignmentType
            );

            if ($result['status'] === 'NO_CASE') {
                break;
            }

            if ($result['status'] === 'NO_AGENT') {
                $noAgent++;

                if ($result['queue_id'] !== null) {
                    $blockedQueueIds[$result['queue_id']] = true;
                }

                // No detenemos todo el proceso por una cola sin agente:
                // otras colas pueden tener agentes elegibles.
                continue;
            }

            $assigned++;
        }

        return [
            'assigned'=>$assigned,
            'no_agent'=>$noAgent,
            'iterations'=>$iterations,
        ];
    }

    /**
     * Recupera únicamente casos previamente asignados al agente que quedaron
     * pendientes por una liberación automática histórica. No toma casos nuevos.
     *
     * @return array{recovered:int,iterations:int}
     */
    public function recoverForAgent(
        int $userId,
        int $maxRecoveries = 500
    ): array {
        $maxRecoveries = max(1, min(5000, $maxRecoveries));
        $recovered = 0;
        $iterations = 0;

        $queueIds = $this->repo->eligibleQueueIdsForAgent($userId);

        foreach ($queueIds as $queueId) {
            while ($recovered < $maxRecoveries) {
                $iterations++;

                $this->pdo->beginTransaction();

                try {
                    $lockedUser = $this->repo->lockUser($userId);

                    if (
                        !$lockedUser
                        || (int)$lockedUser['is_active'] !== 1
                        || (int)$lockedUser['assign_enabled'] !== 1
                        || !$this->repo->isAvailableNow($userId)
                        || !$this->repo->isOnShiftForQueue($queueId, $userId)
                        || !$this->repo->isEligibleForQueue($queueId, $userId)
                    ) {
                        $this->pdo->commit();
                        break;
                    }

                    $capacity = $this->repo->capacityForQueue($queueId, $userId);
                    $openCases = $this->repo->openCaseCount($queueId, $userId);

                    if ($capacity <= 0 || $openCases >= $capacity) {
                        $this->pdo->commit();
                        break;
                    }

                    $limit = min(
                        $capacity - $openCases,
                        $maxRecoveries - $recovered
                    );

                    $caseIds = $this->repo->recoverInterruptedCasesForAgent(
                        $queueId,
                        $userId,
                        $limit
                    );

                    $count = count($caseIds);
                    $this->pdo->commit();

                    if ($count === 0) {
                        break;
                    }

                    $recovered += $count;
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }

                    throw $e;
                }
            }

            if ($recovered >= $maxRecoveries) {
                break;
            }
        }

        return [
            'recovered'=>$recovered,
            'iterations'=>$iterations,
        ];
    }

    /**
     * Reparte inmediatamente a un agente que acaba de pasar a AVAILABLE.
     *
     * La operación queda limitada a las colas en las que el agente es elegible
     * en este momento y respeta la capacidad configurada por cola. El worker
     * continúa funcionando como mecanismo de respaldo para nuevos casos,
     * cambios de presencia y concurrencia.
     *
     * @return array{assigned:int,no_agent:int,iterations:int}
     */
    public function runForAgent(
        int $userId,
        int $maxAssignments = 500
    ): array {
        $maxAssignments = max(1, min(5000, $maxAssignments));

        $assigned = 0;
        $iterations = 0;

        $queueIds = $this->repo->eligibleQueueIdsForAgent($userId);

        foreach ($queueIds as $queueId) {
            while ($assigned < $maxAssignments) {
                $iterations++;

                $this->pdo->beginTransaction();

                try {
                    $lockedUser = $this->repo->lockUser($userId);

                    if (
                        !$lockedUser
                        || (int)$lockedUser['is_active'] !== 1
                        || (int)$lockedUser['assign_enabled'] !== 1
                        || !$this->repo->isAvailableNow($userId)
                        || !$this->repo->isOnShiftForQueue($queueId, $userId)
                        || !$this->repo->isEligibleForQueue($queueId, $userId)
                    ) {
                        $this->pdo->commit();
                        break;
                    }

                    $capacity = $this->repo->capacityForQueue($queueId, $userId);
                    $openCases = $this->repo->openCaseCount($queueId, $userId);

                    if ($capacity <= 0 || $openCases >= $capacity) {
                        $this->pdo->commit();
                        break;
                    }

                    // Antes de tomar casos nuevos, recuperamos los casos que
                    // pertenecían anteriormente a este agente y que quedaron
                    // pendientes por una liberación automática ocurrida antes
                    // de la regla de permanencia. Nunca recuperamos un caso
                    // cuya última asignación histórica pertenezca a otro agente.
                    $recoveryLimit = min(
                        $capacity - $openCases,
                        $maxAssignments - $assigned
                    );

                    $recoveredCaseIds = $this->repo->recoverInterruptedCasesForAgent(
                        $queueId,
                        $userId,
                        $recoveryLimit
                    );

                    if ($recoveredCaseIds !== []) {
                        $assigned += count($recoveredCaseIds);
                        $this->pdo->commit();
                        continue;
                    }

                    $case = $this->repo->nextPendingCaseForUpdate($queueId);

                    if ($case === null) {
                        $this->pdo->commit();
                        break;
                    }

                    $this->repo->assignCase(
                        (int)$case['id'],
                        $queueId,
                        $userId,
                        'AUTO'
                    );

                    $this->pdo->commit();
                    $assigned++;
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }

                    throw $e;
                }
            }

            if ($assigned >= $maxAssignments) {
                break;
            }
        }

        return [
            'assigned'=>$assigned,
            'no_agent'=>0,
            'iterations'=>$iterations,
        ];
    }


    /**
     * Libera explícitamente los casos abiertos de un agente.
     *
     * Esta operación no se ejecuta automáticamente por desconexión, logout,
     * heartbeat vencido ni fin de turno. Puede utilizarse cuando una acción
     * administrativa decide liberar/reasignar casos de forma explícita.
     *
     * @return array{case_ids:list<int>,queue_ids:list<int>}
     */
    public function releaseCasesForAgent(
        int $userId,
        string $reason = 'STALE_HEARTBEAT'
    ): array {
        return $this->repo->releaseCasesForAgent($userId, $reason);
    }


    /**
     * @param array<int,bool> $blockedQueueIds
     * @return array{status:'ASSIGNED'|'NO_AGENT'|'NO_CASE',queue_id:int|null}
     */
    private function assignOne(
        ?int $queueId,
        array $blockedQueueIds,
        string $assignmentType
    ): array
    {
        $this->pdo->beginTransaction();

        try {
            $excludedQueueIds = array_keys($blockedQueueIds);
            $case = $this->repo->nextPendingCaseForUpdate($queueId, $excludedQueueIds);

            if (!$case) {
                $this->pdo->commit();
                return ['status'=>'NO_CASE','queue_id'=>null];
            }

            $caseId = (int)$case['id'];
            $caseQueueId = (int)$case['queue_id'];

            if ($caseQueueId <= 0) {
                throw new \RuntimeException("El caso {$caseId} no tiene cola asignada.");
            }

            $candidates = $this->repo->eligibleCandidateIds($caseQueueId);

            foreach ($candidates as $candidate) {
                $userId = (int)$candidate['id'];

                $lockedUser = $this->repo->lockUser($userId);
                if (
                    !$lockedUser
                    || (int)$lockedUser['is_active'] !== 1
                    || (int)$lockedUser['assign_enabled'] !== 1
                ) {
                    continue;
                }

                if (!$this->repo->isAvailableNow($userId)) {
                    continue;
                }

                if (!$this->repo->isEligibleForQueue($caseQueueId, $userId)) {
                    continue;
                }

                $capacity = $this->repo->capacityForQueue($caseQueueId, $userId);
                if ($capacity <= 0) {
                    continue;
                }

                $open = $this->repo->openCaseCount($caseQueueId, $userId);
                if ($open >= $capacity) {
                    continue;
                }

                $this->repo->assignCase(
                    $caseId,
                    $caseQueueId,
                    $userId,
                    $assignmentType
                );
                $this->pdo->commit();

                return ['status'=>'ASSIGNED','queue_id'=>$caseQueueId];
            }

            $this->pdo->commit();

            return ['status'=>'NO_AGENT','queue_id'=>$caseQueueId];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}