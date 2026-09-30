<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DashboardRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{from:?string,to:?string,queue_id:?int,agent_id:?int} $filters
     * @return array<string,mixed>
     */
    public function data(array $filters): array
    {
        $queueId = $filters['queue_id'] ?? null;
        $agentId = $filters['agent_id'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $currentWhere = $this->caseScope($queueId, $agentId);
        $currentParams = $this->caseScopeParams($queueId, $agentId);

        $current = $this->singleRow(
            "SELECT
                COUNT(*) total_cases,
                SUM(c.closed_at IS NULL AND c.current_state <> 'CLOSED') open_cases,
                SUM(c.closed_at IS NULL AND c.current_state = 'PENDING_ASSIGNMENT') pending_assignment,
                SUM(c.closed_at IS NULL AND c.current_state = 'ASSIGNED') assigned_cases,
                SUM(c.closed_at IS NULL AND c.first_management_at IS NOT NULL AND c.current_state <> 'CLOSED') managed_open,
                SUM(c.closed_at IS NOT NULL OR c.current_state = 'CLOSED') closed_cases,
                SUM(c.closed_at IS NULL AND c.current_state <> 'CLOSED' AND c.sla_status='GREEN') sla_green,
                SUM(c.closed_at IS NULL AND c.current_state <> 'CLOSED' AND c.sla_status='YELLOW') sla_yellow,
                SUM(c.closed_at IS NULL AND c.current_state <> 'CLOSED' AND c.sla_status='RED') sla_red,
                SUM(c.closed_at IS NULL AND c.current_state <> 'CLOSED' AND c.sla_status='BREACHED') sla_breached,
                SUM(c.closed_at IS NULL AND c.current_state <> 'CLOSED' AND c.sla_status IS NULL) sla_pending
             FROM cases c
             {$currentWhere}",
            $currentParams
        );

        $periodWhere = 'WHERE 1=1';
        $periodParams = [];

        if ($from !== null) {
            $periodWhere .= ' AND c.created_at >= :from';
            $periodParams[':from'] = $from;
        }
        if ($to !== null) {
            $periodWhere .= ' AND c.created_at < :to';
            $periodParams[':to'] = $to;
        }
        if ($queueId !== null) {
            $periodWhere .= ' AND c.queue_id=:period_queue_id';
            $periodParams[':period_queue_id'] = $queueId;
        }
        if ($agentId !== null) {
            $periodWhere .= ' AND c.assigned_user_id=:period_agent_id';
            $periodParams[':period_agent_id'] = $agentId;
        }

        $period = $this->singleRow(
            "SELECT
                COUNT(*) received,
                SUM(c.first_management_at IS NOT NULL) managed,
                AVG(CASE
                    WHEN c.first_management_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, COALESCE(c.radicated_at,c.created_at), c.first_management_at)
                    ELSE NULL END) avg_first_management_minutes,
                AVG(CASE
                    WHEN c.closed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, COALESCE(c.radicated_at,c.created_at), c.closed_at)
                    ELSE NULL END) avg_resolution_minutes
             FROM cases c
             {$periodWhere}",
            $periodParams
        );

        $closedWhere = 'WHERE c.closed_at IS NOT NULL';
        $closedParams = [];
        if ($from !== null) {
            $closedWhere .= ' AND c.closed_at >= :closed_from';
            $closedParams[':closed_from'] = $from;
        }
        if ($to !== null) {
            $closedWhere .= ' AND c.closed_at < :closed_to';
            $closedParams[':closed_to'] = $to;
        }
        if ($queueId !== null) {
            $closedWhere .= ' AND c.queue_id=:closed_queue_id';
            $closedParams[':closed_queue_id'] = $queueId;
        }
        if ($agentId !== null) {
            $closedWhere .= ' AND c.assigned_user_id=:closed_agent_id';
            $closedParams[':closed_agent_id'] = $agentId;
        }

        $closedPeriod = (int)$this->scalar(
            "SELECT COUNT(*) FROM cases c {$closedWhere}",
            $closedParams
        );

        $agents = $this->agentSummary();
        $queues = $this->queueSummary($from, $to);
        $managementTypes = $this->managementSummary($from, $to, $queueId, $agentId);
        $regions = $this->dimensionSummary('regional', $from, $to, $queueId, $agentId);
        $channels = $this->dimensionSummary('origin_channel', $from, $to, $queueId, $agentId);
        $trend = $this->dailyTrend(7);
        $alerts = $this->alerts(12, $queueId, $agentId);
        $imports = $this->recentImports(8);
        $presence = $this->presenceSummary();

        $current['received'] = (int)($period['received'] ?? 0);
        $current['closed_period'] = $closedPeriod;
        $current['managed_period'] = (int)($period['managed'] ?? 0);
        $current['avg_first_management_minutes'] = $period['avg_first_management_minutes'] !== null
            ? (float)$period['avg_first_management_minutes']
            : null;
        $current['avg_resolution_minutes'] = $period['avg_resolution_minutes'] !== null
            ? (float)$period['avg_resolution_minutes']
            : null;

        return [
            'summary' => array_map('intval', array_filter($current, static fn($v) => $v !== null)),
            'agents' => $agents,
            'queues' => $queues,
            'management_types' => $managementTypes,
            'regions' => $regions,
            'channels' => $channels,
            'trend' => $trend,
            'alerts' => $alerts,
            'imports' => $imports,
            'presence' => $presence,
            'filters' => [
                'queues' => $this->activeQueues(),
                'agents' => $this->activeAgents(),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function singleRow(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: [];
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

    private function caseScope(?int $queueId, ?int $agentId): string
    {
        $where = "WHERE 1=1";
        if ($queueId !== null) {
            $where .= " AND c.queue_id=:scope_queue_id";
        }
        if ($agentId !== null) {
            $where .= " AND c.assigned_user_id=:scope_agent_id";
        }
        return $where;
    }

    /** @return array<string,int> */
    private function caseScopeParams(?int $queueId, ?int $agentId): array
    {
        $params = [];
        if ($queueId !== null) {
            $params[':scope_queue_id'] = $queueId;
        }
        if ($agentId !== null) {
            $params[':scope_agent_id'] = $agentId;
        }
        return $params;
    }

    /** @return array<string,mixed> */
    private function agentSummary(): array
    {
        $row = $this->singleRow(
            "SELECT
                COUNT(DISTINCT u.id) total_agents,
                COUNT(DISTINCT CASE WHEN u.is_active=1 THEN u.id END) active_agents,
                COUNT(DISTINCT CASE
                    WHEN u.is_active=1 AND u.assign_enabled=1
                    AND ap.status_code='AVAILABLE'
                    AND ap.last_heartbeat_at >= DATE_SUB(NOW(6), INTERVAL 90 SECOND)
                    THEN u.id END) available_agents
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id AND r.code='AGENTE' AND r.is_active=1
             LEFT JOIN agent_presence ap
               ON ap.id=(
                    SELECT ap2.id
                    FROM agent_presence ap2
                    WHERE ap2.user_id=u.id AND ap2.ended_at IS NULL
                    ORDER BY ap2.id DESC LIMIT 1
               )"
        );

        $capacity = (int)$this->scalar(
            "SELECT COALESCE(SUM(COALESCE(qa.capacity_override,q.default_capacity)),0)
             FROM queue_agents qa
             JOIN work_queues q ON q.id=qa.queue_id
             JOIN users u ON u.id=qa.user_id
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id AND r.code='AGENTE' AND r.is_active=1
             WHERE qa.is_enabled=1 AND qa.removed_at IS NULL
               AND q.is_active=1 AND u.is_active=1 AND u.assign_enabled=1"
        );

        $activeLoad = (int)$this->scalar(
            "SELECT COUNT(*)
             FROM cases c
             JOIN users u ON u.id=c.assigned_user_id AND u.is_active=1
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id AND r.code='AGENTE' AND r.is_active=1
             WHERE c.closed_at IS NULL AND c.current_state <> 'CLOSED'"
        );

        $row['configured_capacity'] = $capacity;
        $row['active_load'] = $activeLoad;
        $row['free_capacity'] = max(0, $capacity - $activeLoad);

        $row['total_agents'] = (int)($row['total_agents'] ?? 0);
        $row['active_agents'] = (int)($row['active_agents'] ?? 0);
        $row['available_agents'] = (int)($row['available_agents'] ?? 0);

        return $row;
    }

    /** @return list<array<string,mixed>> */
    private function queueSummary(?string $from, ?string $to): array
    {
        $params = [];
        $period = '';
        if ($from !== null) {
            $period .= ' AND c.created_at >= :q_from';
            $params[':q_from'] = $from;
        }
        if ($to !== null) {
            $period .= ' AND c.created_at < :q_to';
            $params[':q_to'] = $to;
        }

        $sql = "SELECT
                    q.id,q.code,q.name,
                    COUNT(CASE WHEN c.closed_at IS NULL AND c.current_state <> 'CLOSED' THEN 1 END) open_cases,
                    COUNT(CASE WHEN c.closed_at IS NULL AND c.current_state='PENDING_ASSIGNMENT' THEN 1 END) pending_assignment,
                    COUNT(CASE WHEN c.closed_at IS NULL AND c.assigned_user_id IS NOT NULL AND c.current_state <> 'CLOSED' THEN 1 END) assigned_cases,
                    COUNT(CASE WHEN c.closed_at IS NOT NULL THEN 1 END) closed_cases
                FROM work_queues q
                LEFT JOIN cases c ON c.queue_id=q.id {$period}
                WHERE q.is_active=1
                GROUP BY q.id,q.code,q.name
                ORDER BY open_cases DESC,q.priority,q.code";
        return $this->rows($sql, $params);
    }

    /** @return list<array<string,mixed>> */
    private function managementSummary(?string $from, ?string $to, ?int $queueId, ?int $agentId): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        if ($from !== null) {
            $where .= ' AND cm.created_at >= :m_from';
            $params[':m_from'] = $from;
        }
        if ($to !== null) {
            $where .= ' AND cm.created_at < :m_to';
            $params[':m_to'] = $to;
        }
        if ($queueId !== null) {
            $where .= ' AND c.queue_id=:m_queue';
            $params[':m_queue'] = $queueId;
        }
        if ($agentId !== null) {
            $where .= ' AND cm.actor_user_id=:m_agent';
            $params[':m_agent'] = $agentId;
        }

        return $this->rows(
            "SELECT cm.management_type_code code, COUNT(*) total
             FROM case_managements cm
             JOIN cases c ON c.id=cm.case_id
             {$where}
             GROUP BY cm.management_type_code
             ORDER BY total DESC, code",
            $params
        );
    }

    /** @return list<array<string,mixed>> */
    private function dimensionSummary(string $column, ?string $from, ?string $to, ?int $queueId, ?int $agentId): array
    {
        $allowed = ['regional','origin_channel'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }

        $where = 'WHERE 1=1';
        $params = [];
        if ($from !== null) {
            $where .= ' AND c.created_at >= :d_from';
            $params[':d_from'] = $from;
        }
        if ($to !== null) {
            $where .= ' AND c.created_at < :d_to';
            $params[':d_to'] = $to;
        }
        if ($queueId !== null) {
            $where .= ' AND c.queue_id=:d_queue';
            $params[':d_queue'] = $queueId;
        }
        if ($agentId !== null) {
            $where .= ' AND c.assigned_user_id=:d_agent';
            $params[':d_agent'] = $agentId;
        }

        return $this->rows(
            "SELECT COALESCE(NULLIF(TRIM(c.{$column}),''),'Sin dato') label, COUNT(*) total
             FROM cases c
             {$where}
             GROUP BY COALESCE(NULLIF(TRIM(c.{$column}),''),'Sin dato')
             ORDER BY total DESC,label
             LIMIT 10",
            $params
        );
    }

    /** @return list<array<string,mixed>> */
    private function dailyTrend(int $days): array
    {
        $days = max(1, min(31, $days));
        return $this->rows(
            "SELECT
                DATE(c.created_at) day,
                COUNT(*) received,
                SUM(c.closed_at IS NOT NULL) closed
             FROM cases c
             WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY)
             GROUP BY DATE(c.created_at)
             ORDER BY day"
        );
    }

    /** @return list<array<string,mixed>> */
    private function alerts(int $limit, ?int $queueId, ?int $agentId): array
    {
        $limit = max(1, min(50, $limit));
        $where = 'WHERE a.resolved_at IS NULL';
        $params = [];

        if ($queueId !== null) {
            $where .= ' AND c.queue_id=:a_queue';
            $params[':a_queue'] = $queueId;
        }
        if ($agentId !== null) {
            $where .= ' AND c.assigned_user_id=:a_agent';
            $params[':a_agent'] = $agentId;
        }

        return $this->rows(
            "SELECT
                a.id,a.case_id,a.alert_type,a.severity,a.title,a.message,
                a.opened_at,c.case_number,c.external_key,c.sla_status,
                c.sla_due_at,c.sla_elapsed_minutes,
                q.code queue_code,u.full_name assigned_user_name
             FROM case_alerts a
             JOIN cases c ON c.id=a.case_id
             LEFT JOIN work_queues q ON q.id=c.queue_id
             LEFT JOIN users u ON u.id=c.assigned_user_id
             {$where}
             ORDER BY FIELD(a.severity,'CRITICAL','WARNING','INFO'),a.opened_at
             LIMIT {$limit}",
            $params
        );
    }

    /** @return list<array<string,mixed>> */
    private function recentImports(int $limit): array
    {
        $limit = max(1, min(20, $limit));
        return $this->rows(
            "SELECT
                b.id,b.batch_number,b.original_filename,b.status,
                b.total_rows,b.valid_rows,b.invalid_rows,b.duplicate_rows,
                b.uploaded_at,b.confirmed_at,q.code queue_code
             FROM import_batches b
             LEFT JOIN work_queues q ON q.id=b.queue_id
             ORDER BY b.uploaded_at DESC
             LIMIT {$limit}"
        );
    }

    /** @return array<string,int> */
    private function presenceSummary(): array
    {
        $rows = $this->rows(
            "SELECT COALESCE(ap.status_code,'OFFLINE') status_code, COUNT(DISTINCT u.id) total
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id AND r.code='AGENTE' AND r.is_active=1
             LEFT JOIN agent_presence ap
               ON ap.id=(
                    SELECT ap2.id FROM agent_presence ap2
                    WHERE ap2.user_id=u.id AND ap2.ended_at IS NULL
                    ORDER BY ap2.id DESC LIMIT 1
               )
             WHERE u.is_active=1
             GROUP BY COALESCE(ap.status_code,'OFFLINE')
             ORDER BY total DESC"
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['status_code']] = (int)$row['total'];
        }
        return $result;
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
            "SELECT DISTINCT u.id,u.full_name,u.username
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id
             WHERE r.code='AGENTE' AND r.is_active=1 AND u.is_active=1
             ORDER BY u.full_name"
        );
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    }
}
