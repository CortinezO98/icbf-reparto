<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SlaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function activePolicy(): array
    {
        $st = $this->pdo->query(
            "SELECT *
             FROM sla_policies
             WHERE is_active=1
             ORDER BY id
             LIMIT 1"
        );

        $row = $st->fetch();

        if (!$row) {
            throw new \RuntimeException('No existe una política ANS activa.');
        }

        return $row;
    }

    /** @return list<string> */
    public function activeHolidays(): array
    {
        return array_map(
            'strval',
            $this->pdo->query(
                "SELECT holiday_date
                 FROM sla_holidays
                 WHERE is_active=1
                 ORDER BY holiday_date"
            )->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    /** @return array<string,mixed>|null */
    public function caseForEvaluation(int $caseId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT *
             FROM cases
             WHERE id=:id
             LIMIT 1"
        );
        $st->execute([':id'=>$caseId]);

        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function openCases(): array
    {
        return $this->pdo->query(
            "SELECT
                c.id,
                c.case_number,
                c.external_key,
                c.radicated_at,
                c.created_at,
                c.current_state,
                c.first_management_at,
                c.closed_at,
                c.sla_policy_id,
                c.sla_due_at,
                c.sla_status,
                c.sla_elapsed_minutes
             FROM cases c
             WHERE c.closed_at IS NULL
               AND c.current_state <> 'CLOSED'
             ORDER BY COALESCE(c.radicated_at,c.created_at),c.id"
        )->fetchAll() ?: [];
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    public function persistSnapshot(int $caseId, array $snapshot): void
    {
        $st = $this->pdo->prepare(
            "UPDATE cases
             SET sla_policy_id=:policy_id,
                 sla_due_at=:due_at,
                 sla_status=:status,
                 sla_elapsed_minutes=:elapsed_minutes,
                 sla_last_evaluated_at=NOW(6),
                 sla_breached_at=CASE
                    WHEN :status_breached='BREACHED'
                    THEN COALESCE(sla_breached_at,NOW(6))
                    ELSE sla_breached_at
                 END
             WHERE id=:id"
        );

        $st->execute([
            ':policy_id'=>(int)$snapshot['policy_id'],
            ':due_at'=>$snapshot['due_at'],
            ':status'=>$snapshot['status'],
            ':elapsed_minutes'=>(int)$snapshot['elapsed_minutes'],
            ':status_breached'=>$snapshot['status'],
            ':id'=>$caseId,
        ]);
    }

    public function upsertAlert(
        int $caseId,
        string $type,
        string $severity,
        string $title,
        string $message
    ): void {
        $dedupe = 'CASE:' . $caseId . ':' . $type;

        $st = $this->pdo->prepare(
            "INSERT INTO case_alerts
             (case_id,alert_type,severity,title,message,dedupe_key,opened_at,last_seen_at,resolved_at)
             VALUES(:case_id,:type,:severity,:title,:message,:dedupe,NOW(6),NOW(6),NULL)
             ON DUPLICATE KEY UPDATE
                severity=VALUES(severity),
                title=VALUES(title),
                message=VALUES(message),
                last_seen_at=NOW(6),
                resolved_at=NULL"
        );

        $st->execute([
            ':case_id'=>$caseId,
            ':type'=>$type,
            ':severity'=>$severity,
            ':title'=>$title,
            ':message'=>$message,
            ':dedupe'=>$dedupe,
        ]);
    }

    public function resolveAlert(int $caseId, string $type): void
    {
        $st = $this->pdo->prepare(
            "UPDATE case_alerts
             SET resolved_at=COALESCE(resolved_at,NOW(6)),
                 updated_at=NOW(6)
             WHERE case_id=:case_id
               AND alert_type=:type
               AND resolved_at IS NULL"
        );
        $st->execute([
            ':case_id'=>$caseId,
            ':type'=>$type,
        ]);
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $row = $this->pdo->query(
            "SELECT
                SUM(closed_at IS NULL AND current_state<>'CLOSED' AND sla_status='GREEN') green_count,
                SUM(closed_at IS NULL AND current_state<>'CLOSED' AND sla_status='YELLOW') yellow_count,
                SUM(closed_at IS NULL AND current_state<>'CLOSED' AND sla_status='RED') red_count,
                SUM(closed_at IS NULL AND current_state<>'CLOSED' AND sla_status='BREACHED') breached_count,
                SUM(closed_at IS NULL AND current_state<>'CLOSED') open_count
             FROM cases"
        )->fetch() ?: [];

        $alerts = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM case_alerts WHERE resolved_at IS NULL"
        )->fetchColumn();

        return [
            'green'=>(int)($row['green_count'] ?? 0),
            'yellow'=>(int)($row['yellow_count'] ?? 0),
            'red'=>(int)($row['red_count'] ?? 0),
            'breached'=>(int)($row['breached_count'] ?? 0),
            'open'=>(int)($row['open_count'] ?? 0),
            'alerts'=>$alerts,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function openAlerts(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        $sql = <<<SQL
            SELECT
                a.id,
                a.case_id,
                a.alert_type,
                a.severity,
                a.title,
                a.message,
                a.opened_at,
                a.last_seen_at,
                c.case_number,
                c.external_key,
                c.sla_status,
                c.sla_due_at,
                c.sla_elapsed_minutes,
                q.code queue_code,
                u.full_name assigned_user_name
            FROM case_alerts a
            JOIN cases c ON c.id=a.case_id
            LEFT JOIN work_queues q ON q.id=c.queue_id
            LEFT JOIN users u ON u.id=c.assigned_user_id
            WHERE a.resolved_at IS NULL
              AND c.closed_at IS NULL
              AND c.current_state <> 'CLOSED'
            ORDER BY
                FIELD(a.severity,'CRITICAL','WARNING','INFO'),
                a.opened_at
            LIMIT {$limit}
        SQL;

        return $this->pdo->query($sql)->fetchAll() ?: [];
    }
}
