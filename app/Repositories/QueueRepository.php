<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class QueueRepository {
 public function __construct(private PDO $pdo) {}
 /** @return list<array<string,mixed>> */
 public function all(): array {
  $sql = "SELECT
            q.*,
            COUNT(DISTINCT qa.id) agent_count,
            COUNT(DISTINCT qs.id) structure_count,
            GROUP_CONCAT(
              DISTINCT CONCAT(s.name, ' v', v.version_number)
              ORDER BY s.name, v.version_number DESC
              SEPARATOR '||'
            ) structure_labels
          FROM work_queues q
          LEFT JOIN queue_agents qa
            ON qa.queue_id=q.id AND qa.is_enabled=1 AND qa.removed_at IS NULL
          LEFT JOIN queue_structures qs
            ON qs.queue_id=q.id AND qs.is_active=1
          LEFT JOIN import_structure_versions v
            ON v.id=qs.structure_version_id AND v.status='ACTIVE'
          LEFT JOIN import_structures s
            ON s.id=v.structure_id AND s.is_active=1
          GROUP BY q.id
          ORDER BY q.priority DESC,q.name";
  return $this->pdo->query($sql)->fetchAll() ?: [];
}
 /** @return list<array<string,mixed>> */
 public function activeVersions(): array { return $this->pdo->query("SELECT v.id,CONCAT(s.name,' v',v.version_number) label FROM import_structure_versions v JOIN import_structures s ON s.id=v.structure_id WHERE v.status='ACTIVE' AND s.is_active=1 ORDER BY s.name,v.version_number DESC")->fetchAll() ?: []; }
 /** @param array<string,mixed> $d */
 public function create(array $d,int $uid): int { $st=$this->pdo->prepare('INSERT INTO work_queues(code,name,description,default_capacity,priority,created_by) VALUES(:c,:n,:d,:cap,:p,:u)'); $st->execute([':c'=>strtoupper($d['code']),':n'=>$d['name'],':d'=>$d['description'] ?: null,':cap'=>$d['default_capacity'],':p'=>$d['priority'],':u'=>$uid]); return (int)$this->pdo->lastInsertId(); }
 /** @return list<array<string,mixed>> */
 public function agentsWithCapacity(): array {
     $sql = "SELECT
                 qa.queue_id,
                 qa.user_id,
                 q.code queue_code,
                 q.name queue_name,
                 u.full_name agent_name,
                 u.username agent_username,
                 qa.capacity_override,
                 q.default_capacity,
                 (
                     SELECT COUNT(*)
                     FROM cases c
                     WHERE c.queue_id=qa.queue_id
                       AND c.assigned_user_id=qa.user_id
                       AND c.closed_at IS NULL
                       AND c.current_state<>'PENDING_ASSIGNMENT'
                 ) open_cases
             FROM queue_agents qa
             JOIN work_queues q ON q.id=qa.queue_id AND q.is_active=1
             JOIN users u ON u.id=qa.user_id
             WHERE qa.is_enabled=1
               AND qa.removed_at IS NULL
             ORDER BY q.priority,q.code,u.full_name,u.id";
     return $this->pdo->query($sql)->fetchAll() ?: [];
 }
 
 public function updateAgentCapacity(
     int $queueId,
     int $userId,
     ?int $capacityOverride,
     int $actorUserId
 ): void {
     if ($capacityOverride !== null && ($capacityOverride < 1 || $capacityOverride > 1000)) {
         throw new \InvalidArgumentException('La capacidad por agente debe estar entre 1 y 1000 casos.');
     }
 
     $this->pdo->beginTransaction();
     try {
         $st = $this->pdo->prepare(
             "SELECT 1
              FROM queue_agents qa
              JOIN work_queues q ON q.id=qa.queue_id AND q.is_active=1
              JOIN users u ON u.id=qa.user_id AND u.is_active=1
              JOIN user_roles ur ON ur.user_id=u.id
              JOIN roles r ON r.id=ur.role_id AND r.code='AGENTE' AND r.is_active=1
              WHERE qa.queue_id=:queue_id
                AND qa.user_id=:user_id
                AND qa.is_enabled=1
                AND qa.removed_at IS NULL
              LIMIT 1
              FOR UPDATE"
         );
         $st->execute([
             ':queue_id'=>$queueId,
             ':user_id'=>$userId,
         ]);
 
         if (!$st->fetchColumn()) {
             throw new \RuntimeException('El agente no está asociado a la cola seleccionada.');
         }
 
         $up = $this->pdo->prepare(
             "UPDATE queue_agents
              SET capacity_override=:capacity,
                  assigned_by=:actor,
                  assigned_at=NOW(6)
              WHERE queue_id=:queue_id
                AND user_id=:user_id
                AND is_enabled=1
                AND removed_at IS NULL"
         );
         $up->execute([
             ':capacity'=>$capacityOverride,
             ':actor'=>$actorUserId,
             ':queue_id'=>$queueId,
             ':user_id'=>$userId,
         ]);
 
         $this->pdo->commit();
     } catch (\Throwable $e) {
         if ($this->pdo->inTransaction()) {
             $this->pdo->rollBack();
         }
         throw $e;
     }
 }
 
 public function attach(int $qid,int $vid): void {
  $this->pdo->beginTransaction();
  try {
    $check = $this->pdo->prepare(
      "SELECT v.id, s.name, v.version_number
       FROM import_structure_versions v
       JOIN import_structures s ON s.id=v.structure_id AND s.is_active=1
       WHERE v.id=:vid AND v.status='ACTIVE'
       LIMIT 1
       FOR UPDATE"
    );
    $check->execute([':vid'=>$vid]);
    if (!$check->fetch()) {
      throw new \RuntimeException('La estructura seleccionada no está activa o ya no está disponible.');
    }

    $queue = $this->pdo->prepare(
      "SELECT id FROM work_queues
       WHERE id=:qid AND is_active=1
       LIMIT 1
       FOR UPDATE"
    );
    $queue->execute([':qid'=>$qid]);
    if (!$queue->fetchColumn()) {
      throw new \RuntimeException('La cola seleccionada no está activa.');
    }

    $st=$this->pdo->prepare(
      'INSERT INTO queue_structures(queue_id,structure_version_id)
       VALUES(:q,:v)
       ON DUPLICATE KEY UPDATE is_active=1,updated_at=NOW(6)'
    );
    $st->execute([':q'=>$qid,':v'=>$vid]);
    $this->pdo->commit();
  } catch (\Throwable $e) {
    if ($this->pdo->inTransaction()) $this->pdo->rollBack();
    throw $e;
  }
}
}