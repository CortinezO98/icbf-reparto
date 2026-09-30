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
    public function run(?int $queueId = null, int $maxAssignments = 500): array
    {
        $maxAssignments = max(1, min(5000, $maxAssignments));

        $assigned = 0;
        $noAgent = 0;
        $iterations = 0;

        while ($assigned < $maxAssignments) {
            $iterations++;

            $result = $this->assignOne($queueId);

            if ($result === 'NO_CASE') {
                break;
            }

            if ($result === 'NO_AGENT') {
                $noAgent++;
                break;
            }

            $assigned++;
        }

        return [
            'assigned'=>$assigned,
            'no_agent'=>$noAgent,
            'iterations'=>$iterations,
        ];
    }

    /** @return 'ASSIGNED'|'NO_AGENT'|'NO_CASE' */
    private function assignOne(?int $queueId): string
    {
        $this->pdo->beginTransaction();

        try {
            $case = $this->repo->nextPendingCaseForUpdate($queueId);

            if (!$case) {
                $this->pdo->commit();
                return 'NO_CASE';
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

                $this->repo->assignCase($caseId, $caseQueueId, $userId);
                $this->pdo->commit();

                return 'ASSIGNED';
            }

            $this->pdo->commit();

            return 'NO_AGENT';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
