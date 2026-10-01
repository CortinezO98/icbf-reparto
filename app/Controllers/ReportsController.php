<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Repositories\ReportRepository;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        $report = $this->allowed(
            $_GET['report'] ?? 'cases',
            ['cases', 'agents_summary', 'agents_history', 'agents_realtime', 'managements', 'assignments', 'volume_time', 'monthly']
        ) ?? 'cases';

        $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            $format = 'csv';
        }

        $filters = $this->filters();
        $staleSeconds = max(30, (int)($_ENV['AGENT_PRESENCE_STALE_SECONDS'] ?? 90));
        $rows = (new ReportRepository($this->pdo))->reportRows(
            $report,
            $filters,
            $staleSeconds
        );

        $matrix = $this->exportMatrix($report, $rows);
        $filename = $matrix['filename'] . '.' . $format;

        if ($format === 'xlsx') {
            $this->sendExcel(
                $matrix['title'],
                $matrix['headers'],
                $matrix['data'],
                $filename
            );
        }

        $this->sendCsv(
            $matrix['headers'],
            $matrix['data'],
            $filename
        );
    }

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    private function sendCsv(array $headers, array $rows, string $filename): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            throw new \RuntimeException('No fue posible abrir la salida CSV.');
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }

        fclose($out);
        exit;
    }

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    private function sendExcel(
        string $title,
        array $headers,
        array $rows,
        string $filename
    ): never {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('ICBF Reparto')
            ->setTitle($title)
            ->setSubject($title);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');

        $sheet->fromArray([$headers, ...$rows], null, 'A1');

        $columnCount = count($headers);
        $lastColumn = $this->excelColumn($columnCount);
        $lastRow = max(1, count($rows) + 1);

        $headerRange = 'A1:' . $lastColumn . '1';
        $fullRange = 'A1:' . $lastColumn . $lastRow;

        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF0D6EFD');

        $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($fullRange)->getAlignment()->setVertical('top');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($fullRange);

        for ($column = 1; $column <= $columnCount; $column++) {
            $letter = $this->excelColumn($column);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        $sheet->getStyle($fullRange)->getAlignment()->setWrapText(true);

        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    private function excelColumn(int $number): string
    {
        $column = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $column = chr(65 + $remainder) . $column;
            $number = intdiv($number - 1, 26);
        }

        return $column;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *   title:string,
     *   filename:string,
     *   headers:list<string>,
     *   data:list<list<mixed>>
     * }
     */
    private function exportMatrix(string $report, array $rows): array
    {
        $stamp = date('Ymd_His');

        return match ($report) {
            'cases' => [
                'title' => 'Casos y SLA',
                'filename' => 'reporte_casos_sla_' . $stamp,
                'headers' => [
                    'Caso','Clave externa','Tipo petición','Regional','Canal',
                    'Cola','Agente','Estado','Gestión actual','ANS',
                    'Minutos ANS','Vencimiento ANS','Radicado','Creado',
                    'Asignado','Primera gestión','Última gestión','Cerrado'
                ],
                'data' => array_map(
                    static fn(array $row): array => [
                        $row['case_number'] ?? '',
                        $row['external_key'] ?? '',
                        $row['petition_type'] ?? '',
                        $row['regional'] ?? '',
                        $row['origin_channel'] ?? '',
                        $row['queue_code'] ?? '',
                        $row['agent_name'] ?? '',
                        $row['current_state'] ?? '',
                        $row['current_management_type_code'] ?? '',
                        $row['sla_status'] ?? '',
                        $row['sla_elapsed_minutes'] ?? '',
                        $row['sla_due_at'] ?? '',
                        $row['radicated_at'] ?? '',
                        $row['created_at'] ?? '',
                        $row['assigned_at'] ?? '',
                        $row['first_management_at'] ?? '',
                        $row['last_management_at'] ?? '',
                        $row['closed_at'] ?? '',
                    ],
                    $rows
                ),
            ],
            'agents_summary' => [
                'title' => 'Agentes - Resumen',
                'filename' => 'reporte_agentes_resumen_' . $stamp,
                'headers' => [
                    'Agente','Usuario','Asignados','Resueltos','Vencidos',
                    'Tiempo primera gestión (min)','Cumplimiento SLA (%)'
                ],
                'data' => array_map(
                    static function(array $row): array {
                        $closed = (int)($row['closed_cases'] ?? 0);
                        $compliant = (int)($row['compliant_cases'] ?? 0);
                        $compliance = $closed > 0
                            ? round(($compliant / $closed) * 100, 1)
                            : null;

                        return [
                            $row['full_name'] ?? '',
                            $row['username'] ?? '',
                            (int)($row['assigned_cases'] ?? 0),
                            (int)($row['resolved_cases'] ?? 0),
                            (int)($row['breached_cases'] ?? 0),
                            $row['response_minutes'] ?? '',
                            $compliance ?? '',
                        ];
                    },
                    $rows
                ),
            ],
            'agents_history' => [
                'title' => 'Agentes - Histórico Detallado',
                'filename' => 'reporte_agentes_historico_' . $stamp,
                'headers' => [
                    'Agente','Usuario','Estado','Estado descriptivo',
                    'Inicio','Fin','Último heartbeat','Origen',
                    'Establecido por','Duración (min)'
                ],
                'data' => array_map(
                    static fn(array $row): array => [
                        $row['full_name'] ?? '',
                        $row['username'] ?? '',
                        $row['status_code'] ?? '',
                        $row['status_label'] ?? '',
                        $row['started_at'] ?? '',
                        $row['ended_at'] ?? '',
                        $row['last_heartbeat_at'] ?? '',
                        $row['source'] ?? '',
                        $row['set_by_name'] ?? '',
                        (int)($row['duration_minutes'] ?? 0),
                    ],
                    $rows
                ),
            ],
            'agents_realtime' => [
                'title' => 'Agentes - Estado en Tiempo Real',
                'filename' => 'reporte_agentes_tiempo_real_' . $stamp,
                'headers' => [
                    'Agente','Usuario','Estado','Disponible para reparto',
                    'Inicio estado','Último heartbeat','Colas',
                    'Capacidad configurada','Casos abiertos','Capacidad libre'
                ],
                'data' => array_map(
                    static fn(array $row): array => [
                        $row['full_name'] ?? '',
                        $row['username'] ?? '',
                        $row['effective_status'] ?? $row['status_label'] ?? '',
                        (int)($row['effective_available'] ?? 0) === 1 ? 'Sí' : 'No',
                        $row['started_at'] ?? '',
                        $row['last_heartbeat_at'] ?? '',
                        $row['queue_codes'] ?? '',
                        (int)($row['configured_capacity'] ?? 0),
                        (int)($row['open_cases'] ?? 0),
                        (int)($row['free_capacity'] ?? 0),
                    ],
                    $rows
                ),
            ],
            'managements' => [
                'title' => 'Gestiones de Casos',
                'filename' => 'reporte_gestiones_casos_' . $stamp,
                'headers' => [
                    'Caso','Fecha gestión','Agente','Usuario','Tipo gestión',
                    'Escalamiento','Tipo petición seleccionado','Tipo petición anterior',
                    'Tipo petición nuevo','Observación','Soporte','Creado caso',
                    'Estado actual','ANS'
                ],
                'data' => array_map(
                    static fn(array $row): array => [
                        $row['case_number'] ?? '',
                        $row['management_created_at'] ?? '',
                        $row['actor_name'] ?? '',
                        $row['actor_username'] ?? '',
                        $row['management_type_label'] ?? $row['management_type_code'] ?? '',
                        $row['escalation_label'] ?? $row['escalation_category_code'] ?? '',
                        $row['petition_type_selected'] ?? '',
                        $row['previous_petition_type'] ?? '',
                        $row['new_petition_type'] ?? '',
                        $row['observation'] ?? '',
                        $row['support_path'] ?? '',
                        $row['case_created_at'] ?? '',
                        $row['current_state'] ?? '',
                        $row['sla_status'] ?? '',
                    ],
                    $rows
                ),
            ],
            'assignments' => [
                'title' => 'Asignaciones y Reasignaciones',
                'filename' => 'reporte_asignaciones_reasignaciones_' . $stamp,
                'headers' => [
                    'Caso','Cola','Agente','Usuario','Tipo asignación',
                    'Asignado por','Fecha asignación','Fin asignación',
                    'Motivo cierre','Motivo reasignación','Estado actual'
                ],
                'data' => array_map(
                    static fn(array $row): array => [
                        $row['case_number'] ?? '',
                        $row['queue_code'] ?? '',
                        $row['agent_name'] ?? '',
                        $row['agent_username'] ?? '',
                        $row['assignment_type'] ?? '',
                        $row['assigned_by_name'] ?? '',
                        $row['assigned_at'] ?? '',
                        $row['ended_at'] ?? '',
                        $row['end_reason'] ?? '',
                        $row['reassignment_reason'] ?? '',
                        $row['current_state'] ?? '',
                    ],
                    $rows
                ),
            ],
            'volume_time' => [
                'title' => 'Volumen por Día y Hora',
                'filename' => 'reporte_volumen_dia_hora_' . $stamp,
                'headers' => [
                    'Fecha','Hora','Cola','Regional','Tipo petición','Casos'
                ],
                'data' => array_map(
                    static fn(array $row): array => [
                        $row['day'] ?? '',
                        isset($row['hour']) ? str_pad((string)$row['hour'], 2, '0', STR_PAD_LEFT) . ':00' : '',
                        $row['queue_code'] ?? '',
                        $row['regional'] ?? '',
                        $row['petition_type'] ?? '',
                        (int)($row['total_cases'] ?? 0),
                    ],
                    $rows
                ),
            ],
            'monthly' => [
                'title' => 'Consolidado Mensual',
                'filename' => 'reporte_consolidado_mensual_' . $stamp,
                'headers' => [
                    'Mes','Casos','Abiertos','Cerrados','Gestionados',
                    'Vencidos','Tiempo promedio primera gestión (min)',
                    'Tiempo promedio resolución (min)','Cumplimiento ANS (%)'
                ],
                'data' => array_map(
                    static fn(array $row): array => [
                        $row['month_label'] ?? '',
                        (int)($row['total_cases'] ?? 0),
                        (int)($row['open_cases'] ?? 0),
                        (int)($row['closed_cases'] ?? 0),
                        (int)($row['managed_cases'] ?? 0),
                        (int)($row['breached_cases'] ?? 0),
                        $row['avg_response_minutes'] ?? '',
                        $row['avg_resolution_minutes'] ?? '',
                        $row['sla_compliance_percent'] ?? '',
                    ],
                    $rows
                ),
            ],
            default => throw new \InvalidArgumentException('Tipo de reporte no permitido.'),
        };
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
            'regional' => $this->optionalText($_GET['regional'] ?? null),
            'petition_type' => $this->optionalText($_GET['petition_type'] ?? null),
        ];
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value !== '' ? mb_substr($value, 0, 255) : null;
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
