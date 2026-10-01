<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

    /** @phpstan-type ReportFilters array{
     *   from:?string,
     *   to:?string,
     *   queue_id:?int,
     *   agent_id:?int,
     *   state:?string,
     *   sla:?string,
     *   regional?:?string,
     *   petition_type?:?string,
     *   management_type?:?string,
     *   supervisor_id?:?int,
     *   segment?:?string
     * } */

final class ReportRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param ReportFilters $filters
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
                'regionals' => $this->activeRegionals(),
                'supervisors' => $this->activeSupervisors(),
                'segments' => $this->activeSegments(),
                'petition_types' => $this->activePetitionTypes(),
                'management_types' => $this->activeManagementTypes(),
            ],
        ];
    }

    /**
     * @param ReportFilters $filters
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
     * @param ReportFilters $filters
     * @return list<array<string,mixed>>
     */
    public function reportRows(string $report, array $filters, int $staleSeconds = 90): array
    {
        return match ($report) {
            'cases' => $this->casesForExport($filters),
            'agents_summary' => $this->productivity($filters),
            'agents_history' => $this->agentHistory($filters),
            'agents_realtime' => $this->agentRealtime(max(30, $staleSeconds)),
            'managements' => $this->managementsForExport($filters),
            'assignments' => $this->assignmentsForExport($filters),
            'volume_time' => $this->volumeTimeForExport($filters),
            'monthly' => $this->monthlyForExport($filters),
            'police' => $this->policeForExport($filters),
            'escalations' => $this->escalationsForExport($filters),
            'reassignments' => $this->reassignmentsForExport($filters),
            'directed' => $this->directedForExport($filters),
            default => throw new \InvalidArgumentException('Tipo de reporte no permitido.'),
        };
    }

    /**
     * @param ReportFilters $filters
     * @return list<array<string,mixed>>
     */
    private function agentHistory(array $filters): array
    {
        if ($filters['from'] === null || $filters['to'] === null) {
            return [];
        }

        $where = "ap.started_at < :history_to_where\n"
            . " AND (ap.ended_at IS NULL OR ap.ended_at >= :history_from_where)";
        $params = [
            ':history_from_where' => $filters['from'],
            ':history_to_where' => $filters['to'],
            ':history_from_calc' => $filters['from'],
            ':history_to_calc' => $filters['to'],
        ];

        if ($filters['agent_id'] !== null) {
            $where .= ' AND ap.user_id = :history_agent';
            $params[':history_agent'] = $filters['agent_id'];
        }

        return $this->rows(
            "SELECT
                u.full_name,
                u.username,
                ap.status_code,
                COALESCE(ci.label,
                    CASE WHEN ap.status_code='OFFLINE' THEN 'Desconectado' ELSE ap.status_code END
                ) status_label,
                ap.started_at,
                ap.ended_at,
                ap.last_heartbeat_at,
                ap.source,
                setter.full_name set_by_name,
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        MINUTE,
                        GREATEST(ap.started_at,:history_from_calc),
                        LEAST(COALESCE(ap.ended_at,NOW(6)),:history_to_calc)
                    )
                ) duration_minutes
             FROM agent_presence ap
             JOIN users u ON u.id=ap.user_id
             LEFT JOIN users setter ON setter.id=ap.set_by
             LEFT JOIN catalogs cat ON cat.code='AGENT_PRESENCE_STATUS'
             LEFT JOIN catalog_items ci
               ON ci.catalog_id=cat.id
              AND ci.code=ap.status_code
             WHERE {$where}
             ORDER BY ap.started_at DESC,ap.id DESC",
            $params
        );
    }

    /** @return list<array<string,mixed>> */
    private function agentRealtime(int $staleSeconds): array
    {
        $cutoff = (new \DateTimeImmutable())
            ->modify('-' . $staleSeconds . ' seconds')
            ->format('Y-m-d H:i:s.u');

        $rows = $this->rows(
            "SELECT
                u.id,
                u.full_name,
                u.username,
                u.is_active,
                u.assign_enabled,
                COALESCE(ap.status_code,'OFFLINE') status_code,
                COALESCE(ci.label,'Desconectado') status_label,
                ap.started_at,
                ap.last_heartbeat_at,
                GROUP_CONCAT(DISTINCT q.code ORDER BY q.code SEPARATOR ', ') queue_codes,
                COALESCE(SUM(DISTINCT CASE
                    WHEN qa.is_enabled=1 AND qa.removed_at IS NULL
                    THEN COALESCE(qa.capacity_override,q.default_capacity)
                    ELSE 0 END),0) configured_capacity,
                (
                    SELECT COUNT(*)
                    FROM cases c2
                    WHERE c2.assigned_user_id=u.id
                      AND c2.closed_at IS NULL
                      AND c2.current_state<>'PENDING_ASSIGNMENT'
                ) open_cases
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r
               ON r.id=ur.role_id
              AND r.code='AGENTE'
              AND r.is_active=1
             LEFT JOIN agent_presence ap
               ON ap.id=(
                    SELECT ap2.id
                    FROM agent_presence ap2
                    WHERE ap2.user_id=u.id
                      AND ap2.ended_at IS NULL
                    ORDER BY ap2.id DESC
                    LIMIT 1
               )
             LEFT JOIN catalogs cat ON cat.code='AGENT_PRESENCE_STATUS'
             LEFT JOIN catalog_items ci
               ON ci.catalog_id=cat.id
              AND ci.code=ap.status_code
             LEFT JOIN queue_agents qa
               ON qa.user_id=u.id
              AND qa.removed_at IS NULL
             LEFT JOIN work_queues q ON q.id=qa.queue_id
             WHERE u.is_active=1
             GROUP BY
                u.id,u.full_name,u.username,u.is_active,u.assign_enabled,
                ap.status_code,ci.label,ap.started_at,ap.last_heartbeat_at
             ORDER BY u.full_name,u.id"
        );

        foreach ($rows as &$row) {
            $heartbeatFresh = !empty($row['last_heartbeat_at'])
                && (string)$row['last_heartbeat_at'] >= $cutoff;
            $available = (string)$row['status_code'] === 'AVAILABLE'
                && $heartbeatFresh
                && (int)$row['assign_enabled'] === 1;

            $stale = !$heartbeatFresh && (string)$row['status_code'] !== 'OFFLINE';
            $row['effective_status'] = $stale
                ? 'Desconectado'
                : ($available ? 'Disponible' : (string)$row['status_label']);
            $row['effective_available'] = $available ? 1 : 0;
            $row['free_capacity'] = $available
                ? max(0, (int)$row['configured_capacity'] - (int)$row['open_cases'])
                : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param ReportFilters $filters
     */

    /**
     * Detalle de todas las gestiones registradas en el periodo.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function managementsForExport(array $filters): array
    {
        $params = [];
        $where = 'WHERE 1=1';

        if (($filters['from'] ?? null) !== null) {
            $where .= ' AND cm.created_at >= :management_from';
            $params[':management_from'] = $filters['from'];
        }

        if (($filters['to'] ?? null) !== null) {
            $where .= ' AND cm.created_at < :management_to';
            $params[':management_to'] = $filters['to'];
        }

        $this->appendCaseFilter($where, $params, $filters, 'management');

        if (($filters['management_type'] ?? null) !== null) {
            $where .= ' AND cm.management_type_code = :management_type_filter';
            $params[':management_type_filter'] = $filters['management_type'];
        }

        return $this->rows(
            "SELECT
                c.case_number,
                cm.created_at management_created_at,
                actor.full_name actor_name,
                actor.username actor_username,
                cm.management_type_code,
                COALESCE(mti.label,cm.management_type_code) management_type_label,
                cm.escalation_category_code,
                COALESCE(eci.label,cm.escalation_category_code) escalation_label,
                cm.petition_type_selected,
                cm.previous_petition_type,
                cm.new_petition_type,
                cm.observation,
                cm.support_path,
                c.created_at case_created_at,
                c.current_state,
                c.sla_status
             FROM case_managements cm
             JOIN cases c ON c.id=cm.case_id
             JOIN users actor ON actor.id=cm.actor_user_id
             LEFT JOIN catalogs mtc ON mtc.code='CASE_MANAGEMENT_TYPE'
             LEFT JOIN catalog_items mti
               ON mti.catalog_id=mtc.id
              AND mti.code=cm.management_type_code
             LEFT JOIN catalogs esc ON esc.code='ESCALATION_CATEGORY'
             LEFT JOIN catalog_items eci
               ON eci.catalog_id=esc.id
              AND eci.code=cm.escalation_category_code
             {$where}
             ORDER BY cm.created_at DESC,cm.id DESC
             LIMIT 5000",
            $params
        );
    }

    /**
     * Trazabilidad de asignaciones y reasignaciones.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function assignmentsForExport(array $filters): array
    {
        $params = [];
        $where = 'WHERE 1=1';

        if (($filters['from'] ?? null) !== null) {
            $where .= ' AND ca.assigned_at >= :assignment_from';
            $params[':assignment_from'] = $filters['from'];
        }

        if (($filters['to'] ?? null) !== null) {
            $where .= ' AND ca.assigned_at < :assignment_to';
            $params[':assignment_to'] = $filters['to'];
        }

        $this->appendCaseFilter($where, $params, $filters, 'assignment');

        return $this->rows(
            "SELECT
                c.case_number,
                q.code queue_code,
                assigned.full_name agent_name,
                assigned.username agent_username,
                ca.assignment_type,
                assigner.full_name assigned_by_name,
                ca.assigned_at,
                ca.ended_at,
                ca.end_reason,
                COALESCE(
                    (
                        SELECT JSON_UNQUOTE(JSON_EXTRACT(ev.details_json,'$.reason'))
                        FROM case_events ev
                        WHERE ev.case_id=ca.case_id
                          AND ev.event_type='CASE_REASSIGNED'
                          AND JSON_EXTRACT(ev.details_json,'$.to_user_id')=ca.user_id
                          AND ABS(TIMESTAMPDIFF(SECOND,ev.created_at,ca.assigned_at)) <= 5
                        ORDER BY ev.id DESC
                        LIMIT 1
                    ),
                    ''
                ) reassignment_reason,
                c.current_state
             FROM case_assignments ca
             JOIN cases c ON c.id=ca.case_id
             LEFT JOIN work_queues q ON q.id=ca.queue_id
             JOIN users assigned ON assigned.id=ca.user_id
             LEFT JOIN users assigner ON assigner.id=ca.assigned_by
             {$where}
             ORDER BY ca.assigned_at DESC,ca.id DESC
             LIMIT 5000",
            $params
        );
    }

    /**
     * Volumen operativo por día, hora, regional, tipo de petición y cola.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    /**
     * Reporte de reportes a policía, incluyendo ampliaciones.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function policeForExport(array $filters): array
    {
        $params = [];
        $where = "WHERE cm.management_type_code='POLICE_REPORT'";

        if (($filters['from'] ?? null) !== null) {
            $where .= ' AND cm.created_at >= :police_from';
            $params[':police_from'] = $filters['from'];
        }

        if (($filters['to'] ?? null) !== null) {
            $where .= ' AND cm.created_at < :police_to';
            $params[':police_to'] = $filters['to'];
        }

        $this->appendCaseFilter($where, $params, $filters, 'police');

        return $this->rows(
            "SELECT
                c.case_number,
                cm.created_at report_date,
                actor.full_name agent_name,
                supervisor.full_name supervisor_name,
                c.regional,
                c.segment,
                c.petition_type,
                COALESCE(eci.label,cm.escalation_category_code) category,
                CASE WHEN cm.escalation_category_code='EXTENSION' THEN 'Sí' ELSE 'No' END is_extension,
                cm.observation,
                cm.support_path,
                c.current_state
             FROM case_managements cm
             JOIN cases c ON c.id=cm.case_id
             JOIN users actor ON actor.id=cm.actor_user_id
             LEFT JOIN users supervisor ON supervisor.id=actor.supervisor_user_id
             LEFT JOIN catalogs ec ON ec.code='ESCALATION_CATEGORY'
             LEFT JOIN catalog_items eci
               ON eci.catalog_id=ec.id
              AND eci.code=cm.escalation_category_code
             {$where}
             ORDER BY cm.created_at DESC,cm.id DESC
             LIMIT 5000",
            $params
        );
    }

    /**
     * Escalamientos con tiempo hasta resolución/cierre.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function escalationsForExport(array $filters): array
    {
        $params = [];
        $where = "WHERE cm.management_type_code='ESCALATED'";

        if (($filters['from'] ?? null) !== null) {
            $where .= ' AND cm.created_at >= :esc_from';
            $params[':esc_from'] = $filters['from'];
        }

        if (($filters['to'] ?? null) !== null) {
            $where .= ' AND cm.created_at < :esc_to';
            $params[':esc_to'] = $filters['to'];
        }

        $this->appendCaseFilter($where, $params, $filters, 'esc');

        return $this->rows(
            "SELECT
                c.case_number,
                cm.created_at escalation_date,
                actor.full_name agent_name,
                supervisor.full_name supervisor_name,
                COALESCE(eci.label,cm.escalation_category_code) reason,
                COALESCE(
                    (
                        SELECT MIN(cm2.created_at)
                        FROM case_managements cm2
                        WHERE cm2.case_id=cm.case_id
                          AND cm2.management_type_code='CLOSED'
                          AND cm2.created_at>cm.created_at
                    ),
                    c.closed_at
                ) resolved_at,
                CASE
                    WHEN COALESCE(
                        (
                            SELECT MIN(cm3.created_at)
                            FROM case_managements cm3
                            WHERE cm3.case_id=cm.case_id
                              AND cm3.management_type_code='CLOSED'
                              AND cm3.created_at>cm.created_at
                        ),
                        c.closed_at
                    ) IS NULL THEN NULL
                    ELSE TIMESTAMPDIFF(
                        MINUTE,
                        cm.created_at,
                        COALESCE(
                            (
                                SELECT MIN(cm4.created_at)
                                FROM case_managements cm4
                                WHERE cm4.case_id=cm.case_id
                                  AND cm4.management_type_code='CLOSED'
                                  AND cm4.created_at>cm.created_at
                            ),
                            c.closed_at
                        )
                    )
                END resolution_minutes,
                c.current_state
             FROM case_managements cm
             JOIN cases c ON c.id=cm.case_id
             JOIN users actor ON actor.id=cm.actor_user_id
             LEFT JOIN users supervisor ON supervisor.id=actor.supervisor_user_id
             LEFT JOIN catalogs ec ON ec.code='ESCALATION_CATEGORY'
             LEFT JOIN catalog_items eci
               ON eci.catalog_id=ec.id
              AND eci.code=cm.escalation_category_code
             {$where}
             ORDER BY cm.created_at DESC,cm.id DESC
             LIMIT 5000",
            $params
        );
    }

    /**
     * Reasignaciones con agente origen y destino.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function reassignmentsForExport(array $filters): array
    {
        $params = [];
        $where = "WHERE ca.assignment_type='REASSIGN'";

        if (($filters['from'] ?? null) !== null) {
            $where .= ' AND ca.assigned_at >= :reassign_from';
            $params[':reassign_from'] = $filters['from'];
        }

        if (($filters['to'] ?? null) !== null) {
            $where .= ' AND ca.assigned_at < :reassign_to';
            $params[':reassign_to'] = $filters['to'];
        }

        $this->appendCaseFilter($where, $params, $filters, 'reassign');

        return $this->rows(
            "SELECT
                c.case_number,
                q.code queue_code,
                origin.full_name origin_agent,
                destination.full_name destination_agent,
                supervisor.full_name destination_supervisor,
                assigner.full_name assigned_by_name,
                ca.assigned_at,
                ca.ended_at,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(ev.details_json,'$.reason')),
                    ''
                ) reason,
                c.current_state
             FROM case_assignments ca
             JOIN cases c ON c.id=ca.case_id
             LEFT JOIN work_queues q ON q.id=ca.queue_id
             JOIN users destination ON destination.id=ca.user_id
             LEFT JOIN users supervisor ON supervisor.id=destination.supervisor_user_id
             LEFT JOIN users assigner ON assigner.id=ca.assigned_by
             LEFT JOIN case_assignments previous
               ON previous.id=(
                    SELECT MAX(pa.id)
                    FROM case_assignments pa
                    WHERE pa.case_id=ca.case_id
                      AND pa.assigned_at<ca.assigned_at
               )
             LEFT JOIN users origin ON origin.id=previous.user_id
             LEFT JOIN case_events ev
               ON ev.id=(
                    SELECT MAX(ev2.id)
                    FROM case_events ev2
                    WHERE ev2.case_id=ca.case_id
                      AND ev2.event_type='CASE_REASSIGNED'
                      AND JSON_EXTRACT(ev2.details_json,'$.to_user_id')=ca.user_id
                      AND ABS(TIMESTAMPDIFF(SECOND,ev2.created_at,ca.assigned_at))<=5
               )
             {$where}
             ORDER BY ca.assigned_at DESC,ca.id DESC
             LIMIT 5000",
            $params
        );
    }

    /**
     * Direccionamientos registrados como gestión.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function directedForExport(array $filters): array
    {
        $params = [];
        $where = "WHERE cm.management_type_code='DIRECTED'";

        if (($filters['from'] ?? null) !== null) {
            $where .= ' AND cm.created_at >= :directed_from';
            $params[':directed_from'] = $filters['from'];
        }

        if (($filters['to'] ?? null) !== null) {
            $where .= ' AND cm.created_at < :directed_to';
            $params[':directed_to'] = $filters['to'];
        }

        $this->appendCaseFilter($where, $params, $filters, 'directed');

        return $this->rows(
            "SELECT
                c.case_number,
                cm.created_at directed_at,
                actor.full_name agent_name,
                supervisor.full_name supervisor_name,
                q.code queue_code,
                c.regional,
                c.segment,
                c.petition_type,
                cm.observation,
                c.current_state
             FROM case_managements cm
             JOIN cases c ON c.id=cm.case_id
             JOIN users actor ON actor.id=cm.actor_user_id
             LEFT JOIN users supervisor ON supervisor.id=actor.supervisor_user_id
             LEFT JOIN work_queues q ON q.id=c.queue_id
             {$where}
             ORDER BY cm.created_at DESC,cm.id DESC
             LIMIT 5000",
            $params
        );
    }

    private function volumeTimeForExport(array $filters): array
    {
        $where = $this->where($filters);
        $params = $this->params($filters);

        return $this->rows(
            "SELECT
                DATE(c.created_at) day,
                HOUR(c.created_at) hour,
                q.code queue_code,
                c.regional,
                c.petition_type,
                COUNT(*) total_cases
             FROM cases c
             LEFT JOIN work_queues q ON q.id=c.queue_id
             {$where}
             GROUP BY DATE(c.created_at),HOUR(c.created_at),q.code,c.regional,c.petition_type
             ORDER BY day ASC,hour ASC,q.code,c.regional,c.petition_type
             LIMIT 5000",
            $params
        );
    }

    /**
     * Consolidado mensual con indicadores del ciclo de atención.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function monthlyForExport(array $filters): array
    {
        $where = $this->where($filters);
        $params = $this->params($filters);

        return $this->rows(
            "SELECT
                DATE_FORMAT(c.created_at,'%Y-%m') month_key,
                DATE_FORMAT(c.created_at,'%m/%Y') month_label,
                COUNT(*) total_cases,
                SUM(c.current_state <> 'CLOSED' AND c.closed_at IS NULL) open_cases,
                SUM(c.closed_at IS NOT NULL OR c.current_state='CLOSED') closed_cases,
                SUM(c.first_management_at IS NOT NULL) managed_cases,
                SUM(c.closed_at IS NULL AND c.current_state<>'CLOSED') pending_cases,
                SUM(c.sla_status='BREACHED') breached_cases,
                SUM(EXISTS(
                    SELECT 1
                    FROM case_managements cm_dir
                    WHERE cm_dir.case_id=c.id
                      AND cm_dir.management_type_code='DIRECTED'
                )) directed_cases,
                SUM(EXISTS(
                    SELECT 1
                    FROM case_managements cm_pol
                    WHERE cm_pol.case_id=c.id
                      AND cm_pol.management_type_code='POLICE_REPORT'
                )) police_reports,
                SUM(EXISTS(
                    SELECT 1
                    FROM case_managements cm_ext
                    WHERE cm_ext.case_id=c.id
                      AND cm_ext.management_type_code='POLICE_REPORT'
                      AND cm_ext.escalation_category_code='EXTENSION'
                )) police_extensions,
                SUM(EXISTS(
                    SELECT 1
                    FROM case_managements cm_esc
                    WHERE cm_esc.case_id=c.id
                      AND cm_esc.management_type_code='ESCALATED'
                )) escalations,
                SUM((
                    SELECT COUNT(*)
                    FROM case_assignments ca_re
                    WHERE ca_re.case_id=c.id
                      AND ca_re.assignment_type='REASSIGN'
                )) reassignments,
                ROUND(AVG(CASE
                    WHEN c.first_management_at IS NOT NULL
                    THEN TIMESTAMPDIFF(
                        MINUTE,
                        COALESCE(c.assigned_at,c.created_at),
                        c.first_management_at
                    )
                END),1) avg_response_minutes,
                ROUND(AVG(CASE
                    WHEN c.closed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(
                        MINUTE,
                        COALESCE(c.assigned_at,c.created_at),
                        c.closed_at
                    )
                END),1) avg_resolution_minutes,
                ROUND(
                    100 * SUM(
                        CASE
                            WHEN (c.closed_at IS NOT NULL OR c.current_state='CLOSED')
                             AND c.sla_status IN ('GREEN','YELLOW','RED')
                            THEN 1 ELSE 0
                        END
                    ) / NULLIF(
                        SUM(CASE
                            WHEN c.closed_at IS NOT NULL OR c.current_state='CLOSED'
                            THEN 1 ELSE 0
                        END),
                        0
                    ),
                    1
                ) sla_compliance_percent
             FROM cases c
             {$where}
             GROUP BY DATE_FORMAT(c.created_at,'%Y-%m'),DATE_FORMAT(c.created_at,'%m/%Y')
             ORDER BY month_key ASC",
            $params
        );
    }

    /**
     * Agrega filtros comunes de casos a reportes basados en eventos.
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $filters
     */
    private function appendCaseFilter(
        string &$where,
        array &$params,
        array $filters,
        string $prefix
    ): void {
        if (($filters['queue_id'] ?? null) !== null) {
            $where .= " AND c.queue_id = :{$prefix}_queue";
            $params[":{$prefix}_queue"] = $filters['queue_id'];
        }

        if (($filters['agent_id'] ?? null) !== null) {
            $where .= " AND c.assigned_user_id = :{$prefix}_agent";
            $params[":{$prefix}_agent"] = $filters['agent_id'];
        }

        if (($filters['state'] ?? null) !== null) {
            $where .= " AND c.current_state = :{$prefix}_state";
            $params[":{$prefix}_state"] = $filters['state'];
        }

        if (($filters['sla'] ?? null) !== null) {
            $where .= " AND c.sla_status = :{$prefix}_sla";
            $params[":{$prefix}_sla"] = $filters['sla'];
        }

        if (($filters['regional'] ?? null) !== null) {
            $where .= " AND c.regional = :{$prefix}_regional";
            $params[":{$prefix}_regional"] = $filters['regional'];
        }

        if (($filters['petition_type'] ?? null) !== null) {
            $where .= " AND c.petition_type = :{$prefix}_petition_type";
            $params[":{$prefix}_petition_type"] = $filters['petition_type'];
        }

        if (($filters['management_type'] ?? null) !== null) {
            $where .= " AND c.current_management_type_code = :{$prefix}_management_type";
            $params[":{$prefix}_management_type"] = $filters['management_type'];
        }

        if (($filters['supervisor_id'] ?? null) !== null) {
            $where .= " AND EXISTS (
                SELECT 1
                FROM users assigned_agent
                WHERE assigned_agent.id=c.assigned_user_id
                  AND assigned_agent.supervisor_user_id=:{$prefix}_supervisor
            )";
            $params[":{$prefix}_supervisor"] = $filters['supervisor_id'];
        }

        if (($filters['segment'] ?? null) !== null) {
            $where .= " AND c.segment = :{$prefix}_segment";
            $params[":{$prefix}_segment"] = $filters['segment'];
        }
    }

    /**
     * @param ReportFilters $filters
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

        if (($filters['regional'] ?? null) !== null) {
            $where .= ' AND c.regional = :regional';
        }

        if (($filters['petition_type'] ?? null) !== null) {
            $where .= ' AND c.petition_type = :petition_type';
        }

        if (($filters['management_type'] ?? null) !== null) {
            $where .= ' AND c.current_management_type_code = :management_type';
        }

        if (($filters['supervisor_id'] ?? null) !== null) {
            $where .= ' AND EXISTS (
                SELECT 1
                FROM users assigned_agent
                WHERE assigned_agent.id=c.assigned_user_id
                  AND assigned_agent.supervisor_user_id=:supervisor_id
            )';
        }

        if (($filters['segment'] ?? null) !== null) {
            $where .= ' AND c.segment = :segment';
        }

        return $where;
    }

    /**
     * @param ReportFilters $filters
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

        if (($filters['regional'] ?? null) !== null) {
            $params[':regional'] = $filters['regional'];
        }

        if (($filters['petition_type'] ?? null) !== null) {
            $params[':petition_type'] = $filters['petition_type'];
        }

        if (($filters['management_type'] ?? null) !== null) {
            $params[':management_type'] = $filters['management_type'];
        }

        if (($filters['supervisor_id'] ?? null) !== null) {
            $params[':supervisor_id'] = $filters['supervisor_id'];
        }

        if (($filters['segment'] ?? null) !== null) {
            $params[':segment'] = $filters['segment'];
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
                c.id,c.case_number,c.external_key,c.petition_type,c.regional,c.segment,
                c.origin_channel,c.radicated_at,c.created_at,c.assigned_at,
                c.first_management_at,c.last_management_at,c.closed_at,
                c.current_state,c.current_management_type_code,
                c.assigned_user_id,c.sla_status,c.sla_elapsed_minutes,c.sla_due_at,
                q.code queue_code,q.name queue_name,
                u.full_name agent_name,
                supervisor.full_name supervisor_name
             FROM cases c
             LEFT JOIN work_queues q ON q.id=c.queue_id
             LEFT JOIN users u ON u.id=c.assigned_user_id
             LEFT JOIN users supervisor ON supervisor.id=u.supervisor_user_id
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
     * @param ReportFilters $filters
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
     * @param ReportFilters $filters
     * @return list<array<string,mixed>>
     */
    private function productivity(array $filters): array
    {
        $condition = '1=1';
        $params = [];

        $presenceFrom = $filters['from'] ?? '1970-01-01 00:00:00';
        $presenceTo = $filters['to'] ?? date('Y-m-d H:i:s');

        $params[':agent_presence_to_filter'] = $presenceTo;
        $params[':agent_presence_from_filter'] = $presenceFrom;
        $params[':agent_presence_from'] = $presenceFrom;
        $params[':agent_presence_to'] = $presenceTo;

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

        if (($filters['regional'] ?? null) !== null) {
            $condition .= ' AND c.regional = :agent_regional';
            $params[':agent_regional'] = $filters['regional'];
        }

        if (($filters['petition_type'] ?? null) !== null) {
            $condition .= ' AND c.petition_type = :agent_petition_type';
            $params[':agent_petition_type'] = $filters['petition_type'];
        }

        if (($filters['management_type'] ?? null) !== null) {
            $condition .= ' AND c.current_management_type_code = :agent_management_type';
            $params[':agent_management_type'] = $filters['management_type'];
        }

        if (($filters['supervisor_id'] ?? null) !== null) {
            $condition .= ' AND EXISTS (
                SELECT 1
                FROM users assigned_agent
                WHERE assigned_agent.id=c.assigned_user_id
                  AND assigned_agent.supervisor_user_id=:agent_supervisor
            )';
            $params[':agent_supervisor'] = $filters['supervisor_id'];
        }

        if (($filters['segment'] ?? null) !== null) {
            $condition .= ' AND c.segment = :agent_segment';
            $params[':agent_segment'] = $filters['segment'];
        }

        $rows = $this->rows(
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
                END) closed_cases,
                COUNT(DISTINCT CASE
                    WHEN c.first_management_at IS NOT NULL
                    THEN c.id
                END) managed_cases,
                COUNT(DISTINCT CASE
                    WHEN c.closed_at IS NULL AND c.current_state<>'CLOSED'
                    THEN c.id
                END) pending_cases,
                COALESCE(
                    (
                        SELECT SUM(
                            GREATEST(
                                0,
                                TIMESTAMPDIFF(
                                    MINUTE,
                                    GREATEST(ap2.started_at,:agent_presence_from),
                                    LEAST(COALESCE(ap2.ended_at,NOW(6)),:agent_presence_to)
                                )
                            )
                        )
                        FROM agent_presence ap2
                        WHERE ap2.user_id=u.id
                          AND ap2.status_code='AVAILABLE'
                          AND ap2.started_at<:agent_presence_to_filter
                          AND (ap2.ended_at IS NULL OR ap2.ended_at>:agent_presence_from_filter)
                    ),
                    0
                ) available_minutes
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
        $standard = isset($_ENV['PRODUCTIVITY_STANDARD_PER_HOUR'])
            ? (float)$_ENV['PRODUCTIVITY_STANDARD_PER_HOUR']
            : null;

        foreach ($rows as &$row) {
            $availableMinutes = (int)($row['available_minutes'] ?? 0);
            $managedCases = (int)($row['managed_cases'] ?? 0);
            $row['productivity_per_hour'] = $availableMinutes > 0
                ? round($managedCases / ($availableMinutes / 60), 2)
                : null;
            $row['productivity_standard_per_hour'] = $standard;
            $row['productivity_compliance_percent'] =
                $standard !== null && $standard > 0 && $row['productivity_per_hour'] !== null
                    ? round(((float)$row['productivity_per_hour'] / $standard) * 100, 1)
                    : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param ReportFilters $filters
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
     * @param ReportFilters $filters
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
     * @param ReportFilters $filters
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
     * @param ReportFilters $filters
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

        if (($filters['supervisor_id'] ?? null) !== null) {
            $condition .= " AND EXISTS (
                SELECT 1
                FROM users assigned_agent
                WHERE assigned_agent.id=c.assigned_user_id
                  AND assigned_agent.supervisor_user_id=:{$prefix}_supervisor
            )";
            $params[":{$prefix}_supervisor"] = $filters['supervisor_id'];
        }

        if (($filters['segment'] ?? null) !== null) {
            $condition .= " AND c.segment = :{$prefix}_segment";
            $params[":{$prefix}_segment"] = $filters['segment'];
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
    /** @return list<array<string,mixed>> */
    private function activeRegionals(): array
    {
        return $this->rows(
            "SELECT DISTINCT regional
             FROM cases
             WHERE regional IS NOT NULL AND TRIM(regional)<>''
             ORDER BY regional"
        );
    }

    /** @return list<array{id:int,full_name:string}> */
    private function activeSupervisors(): array
    {
        return $this->rows(
            "SELECT DISTINCT u.id,u.full_name
             FROM users u
             JOIN user_roles ur ON ur.user_id=u.id
             JOIN roles r ON r.id=ur.role_id
                AND r.code='SUPERVISOR'
                AND r.is_active=1
             WHERE u.is_active=1
             ORDER BY u.full_name,u.id"
        );
    }

    /** @return list<array{segment:string}> */
    private function activeSegments(): array
    {
        return $this->rows(
            "SELECT DISTINCT segment
             FROM cases
             WHERE segment IS NOT NULL
               AND TRIM(segment)<>''
             ORDER BY segment"
        );
    }

    /** @return list<array<string,mixed>> */
    private function activePetitionTypes(): array
    {
        return $this->rows(
            "SELECT DISTINCT petition_type
             FROM cases
             WHERE petition_type IS NOT NULL AND TRIM(petition_type)<>''
             ORDER BY petition_type"
        );
    }

    /** @return list<array<string,mixed>> */
    private function activeManagementTypes(): array
    {
        return $this->rows(
            "SELECT ci.code,ci.label
             FROM catalog_items ci
             JOIN catalogs c ON c.id=ci.catalog_id
             WHERE c.code='CASE_MANAGEMENT_TYPE'
               AND c.is_active=1
               AND ci.is_active=1
             ORDER BY ci.sort_order,ci.id"
        );
    }

    /** @return list<array{id:int,full_name:string}> */
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