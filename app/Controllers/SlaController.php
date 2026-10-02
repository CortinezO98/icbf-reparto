<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Repositories\DashboardRepository;
use App\Repositories\SlaRepository;
use App\Services\Sla\SlaService;
use PDO;

final class SlaController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'SLA_VIEW');

        $slaRepo = new SlaRepository($this->pdo);

        // Refresh on view so the ANS and alertas remain current even if
        // the worker has not run in the last few minutes.
        try {
            (new SlaService($slaRepo))->evaluateOpenCases();
        } catch (\Throwable $e) {
            error_log('[SLA][DASHBOARD] ' . $e->getMessage());
        }

        $period = $this->period((string)($_GET['period'] ?? 'today'));
        $queueId = $this->nullablePositiveInt($_GET['queue_id'] ?? null);
        $agentId = $this->nullablePositiveInt($_GET['agent_id'] ?? null);

        $dashboard = (new DashboardRepository($this->pdo))->data([
            'from' => $period['from'],
            'to' => $period['to'],
            'queue_id' => $queueId,
            'agent_id' => $agentId,
        ]);
        $dashboard['period'] = $period;

        // Se conserva el contexto específico de ANS dentro del mismo tablero.
        $summary = $slaRepo->summary();
        $alerts = $slaRepo->openAlerts();
        $policy = $slaRepo->activePolicy();

        $view = dirname(__DIR__) . '/Views/sla/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    /** @return array{key:string,label:string,from:?string,to:?string} */
    private function period(string $value): array
    {
        $tz = new \DateTimeZone('America/Bogota');
        $today = new \DateTimeImmutable('today', $tz);
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
