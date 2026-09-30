<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Repositories\AssignmentRepository;
use App\Repositories\AuditRepository;
use App\Repositories\CaseRepository;
use App\Repositories\ImportBatchRepository;
use App\Services\Assignment\AssignmentEngine;
use App\Services\Import\ConfirmImportService;
use App\Services\Import\ImportStagingService;
use PDO;

final class ImportsController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'IMPORT_UPLOAD');
        $repo = new ImportBatchRepository($this->pdo);
        $batches = $repo->recent();
        $versions = $repo->activeVersions();

        $error = $_SESSION['_flash_error'] ?? null;
        $success = $_SESSION['_flash_success'] ?? null;
        unset($_SESSION['_flash_error'], $_SESSION['_flash_success']);

        $view = dirname(__DIR__) . '/Views/imports/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function upload(): void
    {
        Authorization::requirePermission($this->pdo, 'IMPORT_UPLOAD');
        Csrf::validate($_POST['_csrf'] ?? null);

        $versionId = (int)($_POST['structure_version_id'] ?? 0);
        $queueRaw = trim((string)($_POST['queue_id'] ?? ''));
        $queueId = $queueRaw !== '' ? (int)$queueRaw : null;

        if ($versionId <= 0 || !isset($_FILES['import_file'])) {
            $_SESSION['_flash_error'] = 'Selecciona una estructura activa y un archivo.';
            header('Location: /imports');
            exit;
        }

        try {
            $repo = new ImportBatchRepository($this->pdo);
            $batchId = (new ImportStagingService($this->pdo, $repo))->stage(
                $_FILES['import_file'],
                $versionId,
                $queueId,
                (int)Auth::id()
            );

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'IMPORT_BATCH_VALIDATED',
                'IMPORT_BATCH',
                (string)$batchId,
                ['structure_version_id'=>$versionId,'queue_id'=>$queueId]
            );

            header('Location: /imports/' . $batchId);
            exit;
        } catch (\Throwable $e) {
            error_log('[ImportsController::upload] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible procesar la operación solicitada. Revisa los datos e inténtalo nuevamente.';
            header('Location: /imports');
            exit;
        }
    }

    public function show(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'IMPORT_VALIDATE');
        $repo = new ImportBatchRepository($this->pdo);
        $batch = $repo->find($id);

        if (!$batch) {
            http_response_code(404);
            exit('Lote no encontrado.');
        }

        $rows = $repo->rows($id);
        foreach ($rows as &$row) {
            $row['normalized'] = json_decode((string)($row['normalized_json'] ?? '{}'), true) ?: [];
            $row['errors'] = json_decode((string)($row['validation_errors_json'] ?? '[]'), true) ?: [];
        }
        unset($row);

        $error = $_SESSION['_flash_error'] ?? null;
        $success = $_SESSION['_flash_success'] ?? null;
        unset($_SESSION['_flash_error'], $_SESSION['_flash_success']);

        $view = dirname(__DIR__) . '/Views/imports/show.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function confirm(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'IMPORT_CONFIRM');
        Csrf::validate($_POST['_csrf'] ?? null);

        try {
            $batches = new ImportBatchRepository($this->pdo);
            $result = (new ConfirmImportService(
                $this->pdo,
                $batches,
                new CaseRepository($this->pdo)
            ))->confirm($id, (int)Auth::id());

            // El lote ya quedó confirmado. El motor se ejecuta después del
            // commit para repartir inmediatamente los casos que tengan agentes
            // elegibles disponibles. Si no hay agentes, los casos permanecen
            // pendientes y podrán ser tomados por una ejecución posterior.
            $assignment = [
                'assigned'=>0,
                'no_agent'=>0,
                'iterations'=>0,
            ];

            try {
                $assignment = (new AssignmentEngine(
                    $this->pdo,
                    new AssignmentRepository($this->pdo)
                ))->run(null, 500);
            } catch (\Throwable $assignmentError) {
                error_log('[ImportsController::confirm][ASSIGNMENT] ' . $assignmentError->getMessage());
            }

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'IMPORT_BATCH_CONFIRMED',
                'IMPORT_BATCH',
                (string)$id,
                $result + [
                    'assigned_after_import'=>$assignment['assigned'],
                    'pending_without_agent'=>$assignment['no_agent'],
                ]
            );

            $_SESSION['_flash_success'] =
                'Importación confirmada. Casos creados: '
                . $result['created']
                . '. Duplicados omitidos: '
                . $result['duplicates']
                . '. Casos asignados automáticamente: '
                . $assignment['assigned']
                . '.';
        } catch (\Throwable $e) {
            error_log('[ImportsController::confirm] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible procesar la operación solicitada. Revisa los datos e inténtalo nuevamente.';
        }

        header('Location: /imports/' . $id);
        exit;
    }
}
