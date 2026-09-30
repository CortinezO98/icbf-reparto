<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Repositories\DashboardRepository;
use App\Repositories\SlaRepository;
use App\Services\Sla\SlaService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class DashboardController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Auth::requireLogin();

        $uid = (int)(Auth::id() ?? 0);

        // El tablero consolidado contiene información de equipo y operación.
        // Los agentes conservan su acceso normal a la bandeja de casos.
        if (!Authorization::hasPermission($this->pdo, $uid, 'SLA_VIEW')) {
            header('Location: /cases');
            exit;
        }

        // Mantiene el ANS actualizado antes de presentar los indicadores.
        try {
            (new SlaService(new SlaRepository($this->pdo)))->evaluateOpenCases();
        } catch (\Throwable $e) {
            error_log('[DASHBOARD][SLA] ' . $e->getMessage());
        }

        $period = $this->period((string)($_GET['period'] ?? 'today'));
        $queueId = $this->nullablePositiveInt($_GET['queue_id'] ?? null);
        $agentId = $this->nullablePositiveInt($_GET['agent_id'] ?? null);

        $filters = [
            'from' => $period['from'],
            'to' => $period['to'],
            'queue_id' => $queueId,
            'agent_id' => $agentId,
        ];

        $dashboard = (new DashboardRepository($this->pdo))->data($filters);
        $dashboard['period'] = $period;

        $view = dirname(__DIR__) . '/Views/dashboard/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    /** @return array{key:string,label:string,from:?string,to:?string} */
    private function period(string $value): array
    {
        $tz = new DateTimeZone('America/Bogota');
        $today = new DateTimeImmutable('today', $tz);
        $key = in_array($value, ['today','7d','month','all'], true) ? $value : 'today';

        return match ($key) {
            '7d' => [
                'key' => '7d',
                'label' => 'Últimos 7 días',
                'from' => $today->modify('-6 days')->format('Y-m-d 00:00:00'),
                'to' => $today->modify('+1 day')->format('Y-m-d 00:00:00'),
            ],
            'month' => [
                'key' => 'month',
                'label' => 'Mes actual',
                'from' => $today->modify('first day of this month')->format('Y-m-d 00:00:00'),
                'to' => $today->modify('+1 day')->format('Y-m-d 00:00:00'),
            ],
            'all' => [
                'key' => 'all',
                'label' => 'Histórico',
                'from' => null,
                'to' => null,
            ],
            default => [
                'key' => 'today',
                'label' => 'Hoy',
                'from' => $today->format('Y-m-d 00:00:00'),
                'to' => $today->modify('+1 day')->format('Y-m-d 00:00:00'),
            ],
        };
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_scalar($value)) {
            return null;
        }

        $value = (int)$value;
        return $value > 0 ? $value : null;
    }
}
