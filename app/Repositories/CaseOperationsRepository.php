<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CaseOperationsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{
     *   rows:list<array<string,mixed>>,
     *   total:int,
     *   page:int,
     *   per_page:int,
     *   total_pages:int
     * }
     */
    public function paginate(
        int $viewerUserId,
        bool $teamView,
        int $page,
        int $perPage,
        string $search = '',
        string $state = '',
        ?int $queueId = null,
        string $slaStatus = '',
        bool $managed = false
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = 'WHERE 1=1';
        $params = [];

        if (!$teamView) {
            $where .= ' AND c.assigned_user_id=:viewer';
            $params[':viewer'] = $viewerUserId;
        }

        if ($search !== '') {
            $where .= ' AND (
                c.case_number LIKE :s1
                OR c.external_key LIKE :s2
                OR c.petition_type LIKE :s3
            )';
            $like = '%' . trim($search) . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
        }

        if ($state !== '') {
            $where .= ' AND c.current_state=:state';
            $params[':state'] = $state;
        }

        if ($queueId !== null && $queueId > 0) {
            $where .= ' AND c.queue_id=:queue_id';
            $params[':queue_id'] = $queueId;
        }

        if ($slaStatus !== '') {
            if ($slaStatus === 'RED_OR_BREACHED') {
                $where .= " AND c.sla_status IN ('RED','BREACHED')";
            } else {
                $where .= ' AND c.sla_status=:sla_status';
                $params[':sla_status'] = $slaStatus;
            }
        }

        if ($managed) {
            $where .= ' AND c.first_management_at IS NOT NULL';
        }

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM cases c {$where}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $sql = <<<SQL
            SELECT
                c.id,
                c.case_number,
                c.external_key,
                c.petition_type,
                c.regional,
                c.origin_channel,
                c.radicated_at,
                c.current_state,
                c.current_management_type_code,
                c.current_escalation_category_code,
                c.assigned_at,
                c.created_at,
                q.code queue_code,
                q.name queue_name,
                u.full_name assigned_user_name
            FROM cases c
            LEFT JOIN work_queues q ON q.id=c.queue_id
            LEFT JOIN users u ON u.id=c.assigned_user_id
            {$where}
            ORDER BY
                CASE WHEN c.current_state='PENDING_ASSIGNMENT' THEN 0 ELSE 1 END,
                COALESCE(c.radicated_at,c.created_at) ASC,
                c.id ASC
            LIMIT :limit OFFSET :offset
        SQL;

        $st = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value);
        }
        $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        return [
            'rows'=>$st->fetchAll() ?: [],
            'total'=>$total,
            'page'=>$page,
            'per_page'=>$perPage,
            'total_pages'=>max(1, (int)ceil($total / $perPage)),
        ];
    }

    /** @return array<string,mixed>|null */
    public function findCase(int $caseId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT
                c.*,
                q.code queue_code,
                q.name queue_name,
                u.full_name assigned_user_name,
                b.batch_number source_batch_number,
                br.normalized_json source_normalized_json
             FROM cases c
             LEFT JOIN work_queues q ON q.id=c.queue_id
             LEFT JOIN users u ON u.id=c.assigned_user_id
             LEFT JOIN import_batches b ON b.id=c.source_batch_id
             LEFT JOIN import_batch_rows br ON br.id=c.source_batch_row_id
             WHERE c.id=:id
             LIMIT 1"
        );
        $st->execute([':id'=>$caseId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function managements(int $caseId): array
    {
        $st = $this->pdo->prepare(
            "SELECT cm.*,u.full_name actor_name
             FROM case_managements cm
             JOIN users u ON u.id=cm.actor_user_id
             WHERE cm.case_id=:id
             ORDER BY cm.created_at DESC,cm.id DESC"
        );
        $st->execute([':id'=>$caseId]);

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function events(int $caseId): array
    {
        $st = $this->pdo->prepare(
            "SELECT ce.*,u.full_name actor_name
             FROM case_events ce
             LEFT JOIN users u ON u.id=ce.actor_user_id
             WHERE ce.case_id=:id
             ORDER BY ce.created_at DESC,ce.id DESC"
        );
        $st->execute([':id'=>$caseId]);

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function queues(): array
    {
        return $this->pdo->query(
            "SELECT id,code,name FROM work_queues WHERE is_active=1 ORDER BY priority,code"
        )->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function catalogItems(string $catalogCode): array
    {
        $st = $this->pdo->prepare(
            "SELECT ci.code,ci.label
             FROM catalog_items ci
             JOIN catalogs c ON c.id=ci.catalog_id
             WHERE c.code=:code
               AND c.is_active=1
               AND ci.is_active=1
             ORDER BY ci.sort_order,ci.id"
        );
        $st->execute([':code'=>$catalogCode]);

        return $st->fetchAll() ?: [];
    }

    public function canManage(int $caseId, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1
             FROM cases
             WHERE id=:id
               AND assigned_user_id=:uid
               AND closed_at IS NULL
             LIMIT 1"
        );
        $st->execute([':id'=>$caseId, ':uid'=>$userId]);

        return (bool)$st->fetchColumn();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function addManagement(
        int $caseId,
        int $actorUserId,
        array $data
    ): void {
        $this->pdo->beginTransaction();

        try {
            $lock = $this->pdo->prepare(
                "SELECT id,current_state,petition_type,assigned_user_id,closed_at
                 FROM cases
                 WHERE id=:id
                 FOR UPDATE"
            );
            $lock->execute([':id'=>$caseId]);
            $case = $lock->fetch();

            if (!$case) {
                throw new \RuntimeException('Caso no encontrado.');
            }

            if ((int)($case['assigned_user_id'] ?? 0) !== $actorUserId) {
                throw new \RuntimeException('El caso no está asignado al agente actual.');
            }

            if ($case['closed_at'] !== null) {
                throw new \RuntimeException('El caso ya está cerrado.');
            }

            $managementType = (string)$data['management_type_code'];
            $previousPetitionType = (string)($case['petition_type'] ?? '');
            $newPetitionType = $data['new_petition_type'] ?? null;

            $insert = $this->pdo->prepare(
                "INSERT INTO case_managements
                 (
                    case_id,actor_user_id,management_type_code,
                    escalation_category_code,petition_type_selected,
                    previous_petition_type,new_petition_type,
                    observation,support_path,created_at
                 )
                 VALUES
                 (
                    :case_id,:actor,:type,:escalation,:petition_selected,
                    :previous_petition,:new_petition,:observation,:support,NOW(6)
                 )"
            );
            $insert->execute([
                ':case_id'=>$caseId,
                ':actor'=>$actorUserId,
                ':type'=>$managementType,
                ':escalation'=>$data['escalation_category_code'] ?? null,
                ':petition_selected'=>$data['petition_type_selected'] ?? null,
                ':previous_petition'=>$previousPetitionType !== '' ? $previousPetitionType : null,
                ':new_petition'=>$newPetitionType,
                ':observation'=>$data['observation'] ?? null,
                ':support'=>$data['support_path'] ?? null,
            ]);

            $state = (string)$case['current_state'];
            $closed = $managementType === 'CLOSED';

            $update = $this->pdo->prepare(
                "UPDATE cases
                 SET current_management_type_code=:type,
                     current_escalation_category_code=:escalation,
                     petition_type=CASE
                         WHEN :new_petition_flag=1 THEN :new_petition
                         ELSE petition_type
                     END,
                     first_management_at=COALESCE(first_management_at,NOW(6)),
                     last_management_at=NOW(6),
                     current_state=CASE WHEN :closed_flag=1 THEN 'CLOSED' ELSE current_state END,
                     closed_at=CASE WHEN :closed_flag2=1 THEN NOW(6) ELSE closed_at END,
                     updated_at=NOW(6)
                 WHERE id=:id"
            );
            $update->execute([
                ':type'=>$managementType,
                ':escalation'=>$data['escalation_category_code'] ?? null,
                ':new_petition_flag'=>$newPetitionType !== null && $newPetitionType !== '' ? 1 : 0,
                ':new_petition'=>$newPetitionType,
                ':closed_flag'=>$closed ? 1 : 0,
                ':closed_flag2'=>$closed ? 1 : 0,
                ':id'=>$caseId,
            ]);

            $event = $this->pdo->prepare(
                "INSERT INTO case_events
                 (case_id,actor_user_id,event_type,from_state,to_state,details_json,created_at)
                 VALUES(:id,:actor,'CASE_MANAGED',:from_state,:to_state,:details,NOW(6))"
            );
            $event->execute([
                ':id'=>$caseId,
                ':actor'=>$actorUserId,
                ':from_state'=>$state,
                ':to_state'=>$closed ? 'CLOSED' : $state,
                ':details'=>json_encode([
                    'management_type'=>$managementType,
                    'escalation_category'=>$data['escalation_category_code'] ?? null,
                    'new_petition_type'=>$newPetitionType,
                ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);

            if ($closed) {
                $this->pdo->prepare(
                    "UPDATE case_assignments
                     SET ended_at=NOW(6),end_reason='CASE_CLOSED'
                     WHERE case_id=:id
                       AND ended_at IS NULL"
                )->execute([':id'=>$caseId]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
