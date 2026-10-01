<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReportRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return array<string,mixed>
     */
    public function data(array $filters): array
    {
        $where = $this->where($filters);
        $params = $this->params($filters);

        $summary = $this->single(
            "SELECT
                COUNT(*) total_cases,
                SUM(c.current_state <> 'CLOSED' AND c.closed_at IS NULL) open_cases,
                SUM(c.current_state = 'PENDING_ASSIGNMENT' AND c.closed_at IS NULL) pending_assignment,
                SUM(c.assigned_user_id IS NOT NULL AND c.closed_at IS NULL AND c.current_state <> 'CLOSED') assigned_cases,
                SUM(c.first_management_at IS NOT NULL) managed_cases,
                SUM(c.closed_at IS NOT NULL OR c.current_state='CLOSED') closed_cases,
                SUM(c.first_management_at IS NULL AND c.closed_at IS NULL AND c.current_state <> 'CLOSED') unmanaged_open_cases,
                SUM(c.sla_status='GREEN') sla_green,
                SUM(c.sla_status='YELLOW') sla_yellow,
                SUM(c.sla_status='RED') sla_red,
                SUM(c.sla_status='BREACHED') sla_breached,
                AVG(CASE WHEN c.first_management_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, COALESCE(c.assigned_at,c.created_at), c.first_management_at) END) avg_first_management_minutes,
                AVG(CASE WHEN c.closed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, COALESCE(c.assigned_at,c.created_at), c.closed_at) END) avg_resolution_minutes
             FROM cases c
             {$where}",
            $params
        );

        $closedWhere = $where . " AND (c.closed_at IS NOT NULL OR c.current_state='CLOSED')";

        $closedCount = (int)$this->scalar(
            "SELECT COUNT(*) FROM cases c {$closedWhere}",
            $params
        );

        $withinSla = (int)$this->scalar(
            "SELECT COUNT(*) FROM cases c {$closedWhere}
             AND c.sla_status IN ('GREEN','YELLOW','RED')",
            $params
        );

        $summary['sla_compliance_percent'] = $closedCount > 0
            ? round(($withinSla / $closedCount) * 100, 1)
            : null;

        $totalCases = (int)($summary['total_cases'] ?? 0);
        $managedCases = (int)($summary['managed_cases'] ?? 0);
        $summary['response_rate_percent'] = $totalCases > 0
            ? round(($managedCases / $totalCases) * 100, 1)
            : null;

        foreach ([
            'total_cases','open_cases','pending_assignment','assigned_cases',
            'managed_cases','closed_cases','unmanaged_open_cases',
            'sla_green','sla_yellow','sla_red','sla_breached'
        ] as $key) {
            $summary[$key] = (int)($summary[$key] ?? 0);
        }

        return [
            'summary' => $summary,
            'daily' => $this->daily($filters),
            'agents' => $this->productivity($filters),
            'presence_history' => $this->agentHistory($filters),
            'queues' => $this->queues($filters),
            'cases' => $this->cases($where, $params),
            'states' => $this->states($filters),
            'sla' => $this->sla($filters),
            'filters' => [
                'queues' => $this->activeQueues(),
                'agents' => $this->activeAgents(),
            ],
        ];
    }

    /**
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return list<array<string,mixed>>
     */
    public function casesForExport(array $filters): array
    {
        $where = $this->where($filters);

        return $this->cases(
            $where,
            $this->params($filters),
            5000
        );
    }

    /**