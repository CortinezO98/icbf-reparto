<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Repositories\ReportRepository;
use PDO;

final class ReportsController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'REPORT_VIEW');

        $filters = $this->filters();
        $data = (new ReportRepository($this->pdo))->data($filters);

        $data['period'] = $this->periodLabel($filters);
        $data['selected'] = $filters;

        $view = dirname(__DIR__) . '/Views/reports/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function export(): void
    {
        Authorization::requirePermission($this->pdo, 'REPORT_EXPORT');

        $filters = $this->filters();
        $rows = (new ReportRepository($this->pdo))->casesForExport($filters);

        $filename = 'reporte_casos_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            throw new \RuntimeException('No fue posible abrir la salida CSV.');
        }

        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Caso','Clave externa','Tipo petición','Regional','Canal',
            'Cola','Agente','Estado','Gestión actual','ANS',
            'Minutos ANS','Vencimiento ANS','Radicado','Creado',
            'Asignado','Primera gestión','Última gestión','Cerrado'
        ], ';');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['case_number'],
                $row['external_key'],
                $row['petition_type'],
                $row['regional'],
                $row['origin_channel'],
                $row['queue_code'],
                $row['agent_name'],
                $row['current_state'],
                $row['current_management_type_code'],
                $row['sla_status'],
                $row['sla_elapsed_minutes'],
                $row['sla_due_at'],
                $row['radicated_at'],
                $row['created_at'],
                $row['assigned_at'],
                $row['first_management_at'],
                $row['last_management_at'],
                $row['closed_at'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    /**
     * Los reportes trabajan con un rango inclusivo de fechas.
     * Internamente el límite superior se maneja como el día siguiente a
     * las 00:00 para no perder registros del último día seleccionado.
     *
     * @return array{
     *   period:string,
     *   from:?string,
     *   to:?string,
     *   start_date:string,
     *   end_date:string,
     *   queue_id:?int,
     *   agent_id:?int,
     *   state:?string,
     *   sla:?string
     * }
     */
    private function filters(): array
    {
        $tz = new \DateTimeZone('America/Bogota');
        $today = new \DateTimeImmutable('today', $tz);

        $defaultStart = $today->modify('-6 days');
        $startRaw = trim((string)($_GET['start'] ?? $defaultStart->format('Y-m-d')));
        $endRaw = trim((string)($_GET['end'] ?? $today->format('Y-m-d')));

        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $startRaw, $tz);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $endRaw, $tz);

        if (!$start || $start->format('Y-m-d') !== $startRaw) {
            $start = $defaultStart;
        }

        if (!$end || $end->format('Y-m-d') !== $endRaw) {
            $end = $today;
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $endExclusive = $end->modify('+1 day');

        return [
            'period' => 'range',
            'from' => $start->format('Y-m-d 00:00:00'),
            'to' => $endExclusive->format('Y-m-d 00:00:00'),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'queue_id' => $this->positiveInt($_GET['queue_id'] ?? null),
            'agent_id' => $this->positiveInt($_GET['agent_id'] ?? null),
            'state' => $this->allowed(
                $_GET['state'] ?? null,
                ['PENDING_ASSIGNMENT', 'ASSIGNED', 'CLOSED']
            ),
            'sla' => $this->allowed(
                $_GET['sla'] ?? null,
                ['GREEN', 'YELLOW', 'RED', 'BREACHED']
            ),
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_scalar($value)) {
            return null;
        }

        $value = (int)$value;

        return $value > 0 ? $value : null;
    }

    /** @param list<string> $allowed */
    private function allowed(mixed $value, array $allowed): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $value = strtoupper(trim((string)$value));

        return in_array($value, $allowed, true) ? $value : null;
    }

    /** @param array{start_date:string,end_date:string} $filters */
    private function periodLabel(array $filters): string
    {
        $start = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $filters['start_date'],
            new \DateTimeZone('America/Bogota')
        );
        $end = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $filters['end_date'],
            new \DateTimeZone('America/Bogota')
        );

        if (!$start || !$end) {
            return 'Periodo seleccionado';
        }

        return $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
    }
}
