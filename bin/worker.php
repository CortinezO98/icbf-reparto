<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Config\Database;
use App\Repositories\AssignmentRepository;
use App\Repositories\ShiftRepository;
use App\Repositories\SlaRepository;
use App\Repositories\PresenceRepository;
use App\Services\Assignment\AssignmentEngine;
use App\Services\Sla\SlaService;
use App\Services\ShiftService;

$pdo = Database::connection();

$lock = $pdo->query(
    "SELECT GET_LOCK('icbf_reparto_worker', 1)"
)->fetchColumn();

if ((int)$lock !== 1) {
    fwrite(STDERR, "Ya existe otro worker activo.\n");
    exit(1);
}

$intervalSeconds = max(
    5,
    (int)($_ENV['WORKER_INTERVAL_SECONDS'] ?? 10)
);
$slaIntervalSeconds = max(
    60,
    (int)($_ENV['SLA_WORKER_INTERVAL_SECONDS'] ?? 300)
);

$presenceRepository = new PresenceRepository($pdo);

$assignment = new AssignmentEngine(
    $pdo,
    new AssignmentRepository($pdo)
);

$shiftService = new ShiftService(
    $pdo,
    new ShiftRepository($pdo)
);

$slaService = new SlaService(
    new SlaRepository($pdo)
);

echo sprintf(
    "[worker] iniciado | intervalo=%ss | sla=%ss | timezone=%s\n",
    $intervalSeconds,
    $slaIntervalSeconds,
    $_ENV['APP_TIMEZONE'] ?? 'America/Bogota'
);

$lastSlaAt = 0.0;

try {
    for (;;) {
        $startedAt = microtime(true);

        try {
            $presenceResult = $presenceRepository->expireStalePresences(
                max(30, (int)($_ENV['AGENT_PRESENCE_STALE_SECONDS'] ?? 90))
            );

            if ($presenceResult['processed'] > 0) {
                echo sprintf(
                    "[worker] desconexiones detectadas=%d cutoff=%s\n",
                    $presenceResult['processed'],
                    $presenceResult['cutoff']
                );

                foreach ($presenceResult['user_ids'] as $staleUserId) {
                    echo sprintf(
                        "[worker] agente=%d marcado OFFLINE; casos existentes conservados\n",
                        $staleUserId
                    );
                }

            }

            $shiftResult = $shiftService->processEndedShifts();

            if ($shiftResult['processed'] > 0) {
                echo sprintf(
                    "[worker] fin de turno procesado=%d liberados=%d colas=%s\n",
                    $shiftResult['processed'],
                    $shiftResult['released'],
                    $shiftResult['queues'] === []
                        ? '-'
                        : implode(',', $shiftResult['queues'])
                );
            }

            $assignmentResult = $assignment->run(null, 500);

            if (
                $assignmentResult['assigned'] > 0
                || $assignmentResult['no_agent'] > 0
            ) {
                echo sprintf(
                    "[worker] reparto asignados=%d sin_agente=%d iteraciones=%d\n",
                    $assignmentResult['assigned'],
                    $assignmentResult['no_agent'],
                    $assignmentResult['iterations']
                );
            }

            $now = microtime(true);

            if ($now - $lastSlaAt >= $slaIntervalSeconds) {
                $slaResult = $slaService->evaluateOpenCases();

                echo sprintf(
                    "[worker] sla evaluados=%d alertas=%d\n",
                    $slaResult['processed'],
                    $slaResult['alerts_touched']
                );

                $lastSlaAt = $now;
            }
        } catch (\Throwable $e) {
            error_log('[WORKER] ' . $e->getMessage());
            fwrite(
                STDERR,
                '[worker] error: ' . $e->getMessage() . PHP_EOL
            );
        }

        $elapsed = microtime(true) - $startedAt;
        $sleepSeconds = max(1, $intervalSeconds - (int)floor($elapsed));

        sleep($sleepSeconds);
    }
} finally {
    try {
        $pdo->query("SELECT RELEASE_LOCK('icbf_reparto_worker')");
    } catch (\Throwable $e) {
        error_log('[WORKER][LOCK_RELEASE] ' . $e->getMessage());
    }
}