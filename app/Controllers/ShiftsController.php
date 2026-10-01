<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Repositories\AuditRepository;
use App\Repositories\ShiftRepository;
use PDO;

final class ShiftsController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'QUEUE_MANAGE_AGENTS');

        $repo = new ShiftRepository($this->pdo);
        $shifts = $repo->upcoming();
        $agents = $repo->agents();
        $queues = $repo->queues();

        $success = $_SESSION['_flash_success'] ?? null;
        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_success'], $_SESSION['_flash_error']);

        $view = dirname(__DIR__) . '/Views/shifts/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function create(): void
    {
        Authorization::requirePermission($this->pdo, 'QUEUE_MANAGE_AGENTS');
        Csrf::validate($_POST['_csrf'] ?? null);

        $userId = (int)($_POST['user_id'] ?? 0);
        $queueRaw = trim((string)($_POST['queue_id'] ?? ''));
        $queueId = $queueRaw !== '' ? (int)$queueRaw : null;
        $startsAt = trim((string)($_POST['starts_at'] ?? ''));
        $endsAt = trim((string)($_POST['ends_at'] ?? ''));

        if ($userId <= 0 || $startsAt === '' || $endsAt === '') {
            $_SESSION['_flash_error'] = 'Agente, inicio y fin son obligatorios.';
            header('Location: /admin/shifts');
            exit;
        }

        $start = \DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $startsAt,
            new \DateTimeZone((string)($_ENV['APP_TIMEZONE'] ?? 'America/Bogota'))
        );
        $end = \DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $endsAt,
            new \DateTimeZone((string)($_ENV['APP_TIMEZONE'] ?? 'America/Bogota'))
        );

        if ($start === false || $end === false || $end <= $start) {
            $_SESSION['_flash_error'] = 'El rango de turno no es válido.';
            header('Location: /admin/shifts');
            exit;
        }

        try {
            $repo = new ShiftRepository($this->pdo);
            $id = $repo->create(
                $userId,
                $queueId,
                $start->format('Y-m-d H:i:s.u'),
                $end->format('Y-m-d H:i:s.u'),
                (int)(Auth::id() ?? 0)
            );

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'AGENT_SHIFT_CREATED',
                'AGENT_SHIFT',
                (string)$id,
                [
                    'user_id'=>$userId,
                    'queue_id'=>$queueId,
                    'starts_at'=>$start->format('Y-m-d H:i:s.u'),
                    'ends_at'=>$end->format('Y-m-d H:i:s.u'),
                ]
            );

            $_SESSION['_flash_success'] = 'Turno creado correctamente.';
        } catch (\Throwable $e) {
            error_log('[ShiftsController::create] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible crear el turno.';
        }

        header('Location: /admin/shifts');
        exit;
    }

    public function deactivate(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'QUEUE_MANAGE_AGENTS');
        Csrf::validate($_POST['_csrf'] ?? null);

        try {
            (new ShiftRepository($this->pdo))->deactivate($id);

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'AGENT_SHIFT_DEACTIVATED',
                'AGENT_SHIFT',
                (string)$id
            );

            $_SESSION['_flash_success'] = 'Turno desactivado.';
        } catch (\Throwable $e) {
            error_log('[ShiftsController::deactivate] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible desactivar el turno.';
        }

        header('Location: /admin/shifts');
        exit;
    }
}
