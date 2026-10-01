<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;
use App\Repositories\AssignmentRepository;
use App\Repositories\AuditRepository;
use App\Repositories\CaseOperationsRepository;
use App\Services\Assignment\AssignmentEngine;

$interval = max(10, (int)($_ENV['ASSIGNMENT_WORKER_INTERVAL_SECONDS'] ?? 30));
$batchSize = max(1, min(500, (int)($_ENV['ASSIGNMENT_WORKER_BATCH_SIZE'] ?? 100)));

$pdo = Database::connection();

for (;;) {
    try {
        $assignmentRepo = new AssignmentRepository($pdo);
        $caseRepo = new CaseOperationsRepository($pdo);

        $expiredCaseIds = $assignmentRepo->caseIdsOutsideActiveShift($batchSize);

        foreach ($expiredCaseIds as $caseId) {
            try {
                $candidates = $caseRepo->reassignmentCandidates($caseId);

                if ($candidates === []) {
                    continue;
                }

                $targetUserId = (int)$candidates[0]['id'];

                $caseRepo->reassignCase(
                    $caseId,
                    $targetUserId,
                    null,
                    'SHIFT_END'
                );

                (new AuditRepository($pdo))->log(
                    null,
                    'CASE_REASSIGNED_SHIFT_END',
                    'CASE',
                    (string)$caseId,
                    [
                        'new_user_id'=>$targetUserId,
                        'reason'=>'SHIFT_END',
                    ]
                );
            } catch (\Throwable $e) {
                error_log('[AssignmentWorker][REASSIGN] case=' . $caseId . ' ' . $e->getMessage());
            }
        }

        $assignment = (new AssignmentEngine(
            $pdo,
            $assignmentRepo
        ))->run(null, $batchSize);

        if ($assignment['assigned'] > 0 || $assignment['no_agent'] > 0) {
            echo '[' . date('c') . '] asignados='
                . $assignment['assigned']
                . ' sin_agente='
                . $assignment['no_agent']
                . PHP_EOL;
        }
    } catch (\Throwable $e) {
        error_log('[AssignmentWorker] ' . $e->getMessage());
    }

    sleep($interval);
}
