<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class QueueRepository {
 public function __construct(private PDO $pdo) {}
 public function all(): array { return $this->pdo->query("SELECT q.*,COUNT(DISTINCT qa.id) agent_count,COUNT(DISTINCT qs.id) structure_count FROM work_queues q LEFT JOIN queue_agents qa ON qa.queue_id=q.id AND qa.is_enabled=1 LEFT JOIN queue_structures qs ON qs.queue_id=q.id AND qs.is_active=1 GROUP BY q.id ORDER BY q.priority DESC,q.name")->fetchAll() ?: []; }
 public function activeVersions(): array { return $this->pdo->query("SELECT v.id,CONCAT(s.name,' v',v.version_number) label FROM import_structure_versions v JOIN import_structures s ON s.id=v.structure_id WHERE v.status='ACTIVE' AND s.is_active=1 ORDER BY s.name,v.version_number DESC")->fetchAll() ?: []; }
 public function create(array $d,int $uid): int { $st=$this->pdo->prepare('INSERT INTO work_queues(code,name,description,default_capacity,priority,created_by) VALUES(:c,:n,:d,:cap,:p,:u)'); $st->execute([':c'=>strtoupper($d['code']),':n'=>$d['name'],':d'=>$d['description'] ?: null,':cap'=>$d['default_capacity'],':p'=>$d['priority'],':u'=>$uid]); return (int)$this->pdo->lastInsertId(); }
 public function attach(int $qid,int $vid): void { $st=$this->pdo->prepare('INSERT INTO queue_structures(queue_id,structure_version_id) VALUES(:q,:v) ON DUPLICATE KEY UPDATE is_active=1,updated_at=NOW(6)'); $st->execute([':q'=>$qid,':v'=>$vid]); }
}
