<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Repositories\AuditRepository;
use App\Repositories\ShiftRepository;
use PDO;

final class ShiftController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'SHIFT_VIEW');

        $repository = new ShiftRepository($this->pdo);

        $shifts = $repository->allShifts();
        $schedules = $repository->schedules();
        $agents = $repository->allAgents();
        $queues = $repository->allQueues();

        $error = $_SESSION['_flash_error'] ?? null;
        $success = $_SESSION['_flash_success'] ?? null;

        unset($_SESSION['_flash_error'], $_SESSION['_flash_success']);

        $view = dirname(__DIR__) . '/Views/shifts/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function createShift(): void
    {
        Authorization::requirePermission($this->pdo, 'SHIFT_MANAGE');
        Csrf::validate($_POST['_csrf'] ?? null);

        try {
            $repository = new ShiftRepository($this->pdo);

            $id = $repository->createShift(
                (string)($_POST['code'] ?? ''),
                (string)($_POST['name'] ?? ''),
                (string)($_POST['start_time'] ?? ''),
                (string)($_POST['end_time'] ?? ''),
                (int)Auth::id()
            );

            (new AuditRepository($this->pdo))->log(
                (int)Auth::id(),
                'SHIFT_CREATED',
                'WORK_SHIFT',
                (string)$id,
                [
                    'code'=>strtoupper(trim((string)($_POST['code'] ?? ''))),
                    'start_time'=>(string)($_POST['start_time'] ?? ''),
                    'end_time'=>(string)($_POST['end_time'] ?? ''),
                ]
            );

            $_SESSION['_flash_success'] = 'Turno creado correctamente.';
        } catch (\Throwable $e) {
            error_log('[SHIFT_CREATE] ' . $e->getMessage());
            $_SESSION['_flash_error'] = $e instanceof \InvalidArgumentException
                ? $e->getMessage()
                : 'No fue posible crear el turno.';
        }

        header('Location: /admin/shifts');
        exit;
    }

    public function createSchedule(): void
    {
        Authorization::requirePermission($this->pdo, 'SHIFT_MANAGE');
        Csrf::validate($_POST['_csrf'] ?? null);

        try {
            $mode = strtoupper(trim((string)($_POST['schedule_mode'] ?? 'WEEKLY')));

            $scheduleDate = null;
            $weekday = null;

            if ($mode === 'DATE') {
                $scheduleDate = trim((string)($_POST['schedule_date'] ?? ''));
                if ($scheduleDate === '') {
                    throw new \InvalidArgumentException(
                        'Selecciona una fecha específica.'
                    );
                }
            } else {
                $weekday = (int)($_POST['weekday'] ?? 0);
                if ($weekday < 1 || $weekday > 7) {
                    throw new \InvalidArgumentException(
                        'Selecciona un día de semana.'
                    );
                }
            }

            $repository = new ShiftRepository($this->pdo);

            $id = $repository->createSchedule(
                (int)($_POST['user_id'] ?? 0),
                (int)($_POST['queue_id'] ?? 0),
                (int)($_POST['shift_id'] ?? 0),
                $scheduleDate,
                $weekday,
                trim((string)($_POST['valid_from'] ?? '')),
                trim((string)($_POST['valid_to'] ?? '')),
                (int)Auth::id()
            );

            (new AuditRepository($this->pdo))->log(
                (int)Auth::id(),
                'AGENT_SHIFT_SCHEDULE_CREATED',
                'AGENT_SHIFT_SCHEDULE',
                (string)$id,
                [
                    'user_id'=>(int)($_POST['user_id'] ?? 0),
                    'queue_id'=>(int)($_POST['queue_id'] ?? 0),
                    'shift_id'=>(int)($_POST['shift_id'] ?? 0),
                    'schedule_mode'=>$mode,
                    'schedule_date'=>$scheduleDate,
                    'weekday'=>$weekday,
                ]
            );

            $_SESSION['_flash_success'] = 'Cronograma asignado correctamente.';
        } catch (\Throwable $e) {
            error_log('[SHIFT_SCHEDULE_CREATE] ' . $e->getMessage());
            $_SESSION['_flash_error'] = $e instanceof \InvalidArgumentException
                ? $e->getMessage()
                : 'No fue posible crear el cronograma.';
        }

        header('Location: /admin/shifts');
        exit;
    }

    public function toggleSchedule(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'SHIFT_MANAGE');
        Csrf::validate($_POST['_csrf'] ?? null);

        try {
            $repository = new ShiftRepository($this->pdo);
            $schedule = $repository->findSchedule($id);

            if ($schedule === null) {
                throw new \RuntimeException('Cronograma no encontrado.');
            }

            $repository->toggleSchedule($id);

            (new AuditRepository($this->pdo))->log(
                (int)Auth::id(),
                'AGENT_SHIFT_SCHEDULE_TOGGLED',
                'AGENT_SHIFT_SCHEDULE',
                (string)$id,
                [
                    'previous_active'=>(int)$schedule['is_active'],
                    'new_active'=>(int)$schedule['is_active'] === 1 ? 0 : 1,
                ]
            );

            $_SESSION['_flash_success'] = 'Estado del cronograma actualizado.';
        } catch (\Throwable $e) {
            error_log('[SHIFT_SCHEDULE_TOGGLE] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible actualizar el cronograma.';
        }

        header('Location: /admin/shifts');
        exit;
    }
}
