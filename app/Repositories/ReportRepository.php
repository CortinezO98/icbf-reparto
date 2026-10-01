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
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     */
    private function where(array $filters): string
    {
        $where = 'WHERE 1=1';

        if ($filters['from'] !== null) {
            $where .= ' AND c.created_at >= :from';
        }

        if ($filters['to'] !== null) {
            $where .= ' AND c.created_at < :to';
        }

        if ($filters['queue_id'] !== null) {
            $where .= ' AND c.queue_id = :queue_id';
        }

        if ($filters['agent_id'] !== null) {
            $where .= ' AND c.assigned_user_id = :agent_id';
        }

        if ($filters['state'] !== null) {
            $where .= ' AND c.current_state = :state';
        }

        if ($filters['sla'] !== null) {
            $where .= ' AND c.sla_status = :sla';
        }

        return $where;
    }

    /**
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return array<string,mixed>
     */
    private function params(array $filters): array
    {
        $params = [];

        if ($filters['from'] !== null) {
            $params[':from'] = $filters['from'];
        }

        if ($filters['to'] !== null) {
            $params[':to'] = $filters['to'];
        }

        if ($filters['queue_id'] !== null) {
            $params[':queue_id'] = $filters['queue_id'];
        }

        if ($filters['agent_id'] !== null) {
            $params[':agent_id'] = $filters['agent_id'];
        }

        if ($filters['state'] !== null) {
            $params[':state'] = $filters['state'];
        }

        if ($filters['sla'] !== null) {
            $params[':sla'] = $filters['sla'];
        }

        return $params;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function single(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetch() ?: [];
    }

    /** @param array<string,mixed> $params */
    private function scalar(string $sql, array $params = []): mixed
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchColumn();
    }

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    private function cases(string $where, array $params, int $limit = 200): array
    {
        $limit = max(1, min(5000, $limit));

        $st = $this->pdo->prepare(
            "SELECT
                c.id,c.case_number,c.external_key,c.petition_type,c.regional,
                c.origin_channel,c.radicated_at,c.created_at,c.assigned_at,
                c.first_management_at,c.last_management_at,c.closed_at,
                c.current_state,c.current_management_type_code,
                c.assigned_user_id,c.sla_status,c.sla_elapsed_minutes,c.sla_due_at,
                q.code queue_code,q.name queue_name,
                u.full_name agent_name
             FROM cases c
             LEFT JOIN work_queues q ON q.id=c.queue_id
             LEFT JOIN users u ON u.id=c.assigned_user_id
             {$where}
             ORDER BY c.created_at DESC,c.id DESC
             LIMIT {$limit}"
        );

        $st->execute($params);

        return $st->fetchAll() ?: [];
    }

    /**
     * Serie diaria para el rango seleccionado.
     *
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return list<array<string,mixed>>
     */
    private function daily(array $filters): array
    {
        $where = $this->where($filters);

        return $this->rows(
            "SELECT
                DATE(c.created_at) day,
                COUNT(*) total
             FROM cases c
             {$where}
             GROUP BY DATE(c.created_at)
             ORDER BY day ASC",
            $this->params($filters)
        );
    }

    /**
     * Productividad por agente.
     *
     * El tiempo de respuesta se calcula desde la asignación hasta la
     * primera gestión. El cumplimiento ANS se calcula sobre los casos
     * cerrados que terminaron dentro del objetivo registrado.
     *
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return list<array<string,mixed>>
     */
    private function productivity(array $filters): array
    {
        $condition = '1=1';
        $params = [];

        if ($filters['from'] !== null) {
            $condition .= ' AND c.created_at >= :agent_from';
            $params[':agent_from'] = $filters['from'];
        }

        if ($filters['to'] !== null) {
            $condition .= ' AND c.created_at < :agent_to';
            $params[':agent_to'] = $filters['to'];
        }

        if ($filters['queue_id'] !== null) {
            $condition .= ' AND c.queue_id = :agent_queue';
            $params[':agent_queue'] = $filters['queue_id'];
        }

        if ($filters['agent_id'] !== null) {
            $condition .= ' AND c.assigned_user_id = :agent_id_filter';
            $params[':agent_id_filter'] = $filters['agent_id'];
        }

        if ($filters['state'] !== null) {
            $condition .= ' AND c.current_state = :agent_state';
            $params[':agent_state'] = $filters['state'];
        }

        if ($filters['sla'] !== null) {
            $condition .= ' AND c.sla_status = :agent_sla';
            $params[':agent_sla'] = $filters['sla'];
        }

        return $this->rows(
            "SELECT
                u.id,
                u.full_name,
                u.username,
                COUNT(DISTINCT c.id) assigned_cases,
                COUNT(DISTINCT CASE
                    WHEN c.closed_at IS NOT NULL OR c.current_state='CLOSED'
                    THEN c.id
                END) resolved_cases,
                COUNT(DISTINCT CASE
                    WHEN c.sla_status='BREACHED'
                    THEN c.id
                END) breached_cases,
                AVG(CASE
                    WHEN c.first_management_at IS NOT NULL
                    THEN TIMESTAMPDIFF(
                        MINUTE,
                        COALESCE(c.assigned_at,c.created_at),
                        c.first_management_at
                    )
                END) response_minutes,
                COUNT(DISTINCT CASE
                    WHEN (c.closed_at IS NOT NULL OR c.current_state='CLOSED')
                     AND c.sla_status IN ('GREEN','YELLOW','RED')
                    THEN c.id
                END) compliant_cases,
                COUNT(DISTINCT CASE
                    WHEN c.closed_at IS NOT NULL OR c.current_state='CLOSED'
                    THEN c.id
                END) closed_cases
             FROM users u
             JOIN user_roles ur
               ON ur.user_id=u.id
             JOIN roles r
               ON r.id=ur.role_id
              AND r.code='AGENTE'
              AND r.is_active=1
             LEFT JOIN cases c
               ON c.assigned_user_id=u.id
              AND {$condition}
             WHERE u.is_active=1
             GROUP BY u.id,u.full_name,u.username
             HAVING assigned_cases > 0
             ORDER BY assigned_cases DESC,u.full_name",
            $params
        );
    }

    /**
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return list<array<string,mixed>>
     */
    private function queues(array $filters): array
    {
        [$condition, $params] = $this->periodCondition($filters, 'queue');

        return $this->rows(
            "SELECT
                q.code,
                q.name,
                COUNT(DISTINCT CASE
                    WHEN c.current_state <> 'CLOSED' AND c.closed_at IS NULL
                    THEN c.id
                END) open_cases,
                COUNT(DISTINCT CASE
                    WHEN c.current_state='PENDING_ASSIGNMENT' AND c.closed_at IS NULL
                    THEN c.id
                END) pending_assignment,
                COUNT(DISTINCT CASE
                    WHEN c.closed_at IS NOT NULL OR c.current_state='CLOSED'
                    THEN c.id
                END) closed_cases,
                COUNT(DISTINCT CASE WHEN {$condition} THEN c.id END) received_period
             FROM work_queues q
             LEFT JOIN cases c
               ON c.queue_id=q.id
             WHERE q.is_active=1
             GROUP BY q.id,q.code,q.name
             ORDER BY received_period DESC,q.code",
            $params
        );
    }

    /**
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return list<array<string,mixed>>
     */
    private function states(array $filters): array
    {
        $where = $this->where($filters);

        return $this->rows(
            "SELECT
                c.current_state label,
                COUNT(*) total
             FROM cases c
             {$where}
             GROUP BY c.current_state
             ORDER BY total DESC,c.current_state",
            $this->params($filters)
        );
    }

    /**
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return list<array<string,mixed>>
     */
    private function sla(array $filters): array
    {
        $where = $this->where($filters);

        return $this->rows(
            "SELECT
                COALESCE(c.sla_status,'PENDING') label,
                COUNT(*) total
             FROM cases c
             {$where}
             GROUP BY COALESCE(c.sla_status,'PENDING')
             ORDER BY FIELD(
                COALESCE(c.sla_status,'PENDING'),
                'GREEN','YELLOW','RED','BREACHED','PENDING'
             )",
            $this->params($filters)
        );
    }

    /**
     * @param array{
     *   from:?string,to:?string,queue_id:?int,agent_id:?int,state:?string,sla:?string
     * } $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function periodCondition(array $filters, string $prefix): array
    {
        $condition = '1=1';
        $params = [];

        if ($filters['from'] !== null) {
            $condition .= " AND c.created_at >= :{$prefix}_from";
            $params[":{$prefix}_from"] = $filters['from'];
        }

        if ($filters['to'] !== null) {
            $condition .= " AND c.created_at < :{$prefix}_to";
            $params[":{$prefix}_to"] = $filters['to'];
        }

        if ($filters['queue_id'] !== null) {
            $condition .= " AND c.queue_id = :{$prefix}_queue";
            $params[":{$prefix}_queue"] = $filters['queue_id'];
        }

        if ($filters['agent_id'] !== null) {
            $condition .= " AND c.assigned_user_id = :{$prefix}_agent";
            $params[":{$prefix}_agent"] = $filters['agent_id'];
        }

        if ($filters['state'] !== null) {
            $condition .= " AND c.current_state = :{$prefix}_state";
            $params[":{$prefix}_state"] = $filters['state'];
        }

        if ($filters['sla'] !== null) {
            $condition .= " AND c.sla_status = :{$prefix}_sla";
            $params[":{$prefix}_sla"] = $filters['sla'];
        }

        return [$condition, $params];
    }

    /** @return list<array<string,mixed>> */
    private function activeQueues(): array
    {
        return $this->rows(
            "SELECT id,code,name
             FROM work_queues
             WHERE is_active=1
             ORDER BY priority,code"
        );
    }

    /** @return list<array<string,mixed>> */
    private function activeAgents(): array
    {
        return $this->rows(
            "SELECT u.id,u.full_name
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r
               ON r.id=ur.role_id
              AND r.code='AGENTE'
              AND r.is_active=1
             WHERE u.is_active=1
             ORDER BY u.full_name"
        );
    }

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll() ?: [];
    }
}
