<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class ImportStructureRepository {
 public function __construct(private PDO $pdo) {}
 public function all(): array {
  return $this->pdo->query("SELECT s.id,s.code,s.name,s.description,s.is_active,COUNT(v.id) version_count,MAX(CASE WHEN v.status='ACTIVE' THEN v.version_number END) active_version FROM import_structures s LEFT JOIN import_structure_versions v ON v.structure_id=s.id GROUP BY s.id ORDER BY s.name")->fetchAll() ?: [];
 }
 public function find(int $id): ?array { $st=$this->pdo->prepare('SELECT * FROM import_structures WHERE id=:id'); $st->execute([':id'=>$id]); return $st->fetch() ?: null; }
 public function versions(int $sid): array { $st=$this->pdo->prepare('SELECT * FROM import_structure_versions WHERE structure_id=:sid ORDER BY version_number DESC'); $st->execute([':sid'=>$sid]); return $st->fetchAll() ?: []; }
 public function fields(int $vid): array { $st=$this->pdo->prepare('SELECT * FROM import_structure_fields WHERE structure_version_id=:vid ORDER BY sort_order,id'); $st->execute([':vid'=>$vid]); return $st->fetchAll() ?: []; }
 public function create(string $code,string $name,?string $description,int $uid): int { $st=$this->pdo->prepare('INSERT INTO import_structures(code,name,description,created_by) VALUES(:c,:n,:d,:u)'); $st->execute([':c'=>strtoupper($code),':n'=>$name,':d'=>$description ?: null,':u'=>$uid]); return (int)$this->pdo->lastInsertId(); }
 public function createVersion(int $sid,array $d,int $uid): int {
  $this->pdo->beginTransaction(); try {
   $st=$this->pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM import_structure_versions WHERE structure_id=:sid'); $st->execute([':sid'=>$sid]); $n=(int)$st->fetchColumn();
   $st=$this->pdo->prepare('INSERT INTO import_structure_versions(structure_id,version_number,status,target_sheet_mode,target_sheet_value,header_row,data_start_row,external_key_field_code,allow_csv,allow_xlsx,notes,created_by) VALUES(:sid,:v,"DRAFT",:m,:tv,:hr,:dr,:ek,:csv,:xlsx,:notes,:uid)');
   $st->execute([':sid'=>$sid,':v'=>$n,':m'=>$d['target_sheet_mode'],':tv'=>$d['target_sheet_value'] ?: null,':hr'=>$d['header_row'],':dr'=>$d['data_start_row'],':ek'=>$d['external_key_field_code'],':csv'=>$d['allow_csv'],':xlsx'=>$d['allow_xlsx'],':notes'=>$d['notes'] ?: null,':uid'=>$uid]);
   $id=(int)$this->pdo->lastInsertId(); $this->pdo->commit(); return $id;
  } catch(\Throwable $e){ if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
 }
 public function addField(int $vid,array $d): int {
  $aliases=array_values(array_filter(array_map('trim',preg_split('/[\r\n,;]+/',$d['header_aliases']) ?: [])));
  $st=$this->pdo->prepare('INSERT INTO import_structure_fields(structure_version_id,field_code,display_name,excel_header,header_aliases_json,data_type,is_required,is_external_key,is_reportable,max_length,validation_regex,date_format,sort_order) VALUES(:vid,:fc,:dn,:eh,:ha,:dt,:req,:ext,:rep,:ml,:rx,:df,:so)');
  $st->execute([':vid'=>$vid,':fc'=>$d['field_code'],':dn'=>$d['display_name'],':eh'=>$d['excel_header'],':ha'=>$aliases?json_encode($aliases,JSON_UNESCAPED_UNICODE):null,':dt'=>$d['data_type'],':req'=>$d['is_required'],':ext'=>$d['is_external_key'],':rep'=>$d['is_reportable'],':ml'=>$d['max_length'] ?: null,':rx'=>$d['validation_regex'] ?: null,':df'=>$d['date_format'] ?: null,':so'=>$d['sort_order']]);
  return (int)$this->pdo->lastInsertId();
 }
 public function activate(int $sid,int $vid,int $uid): void {
  $this->pdo->beginTransaction(); try {
   $st=$this->pdo->prepare('SELECT id FROM import_structure_versions WHERE id=:vid AND structure_id=:sid FOR UPDATE'); $st->execute([':vid'=>$vid,':sid'=>$sid]); if(!$st->fetchColumn()) throw new \RuntimeException('Versión no encontrada.');
   $st=$this->pdo->prepare('SELECT COUNT(*) total,SUM(is_external_key=1) ext FROM import_structure_fields WHERE structure_version_id=:vid AND is_active=1'); $st->execute([':vid'=>$vid]); $c=$st->fetch();
   if((int)$c['total']<1) throw new \RuntimeException('La versión debe tener al menos un campo.');
   if((int)$c['ext']!==1) throw new \RuntimeException('La versión debe tener exactamente una llave externa.');
   $this->pdo->prepare('UPDATE import_structure_versions SET status="INACTIVE" WHERE structure_id=:sid AND status="ACTIVE"')->execute([':sid'=>$sid]);
   $this->pdo->prepare('UPDATE import_structure_versions SET status="ACTIVE",activated_by=:uid,activated_at=NOW(6) WHERE id=:vid')->execute([':uid'=>$uid,':vid'=>$vid]);
   $this->pdo->commit();
  } catch(\Throwable $e){ if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
 }
}
