<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Auth; use App\Auth\Authorization; use App\Auth\Csrf; use App\Repositories\AuditRepository; use App\Repositories\ImportStructureRepository; use App\Services\Import\StructureVersionBuilder; use PDO;
final class ImportStructuresController {
 public function __construct(private PDO $pdo) {}
 public function index(): void { Authorization::requirePermission($this->pdo,'STRUCTURE_VIEW'); $structures=(new ImportStructureRepository($this->pdo))->all(); $view=dirname(__DIR__).'/Views/structures/index.php'; require dirname(__DIR__).'/Views/layout.php'; }
 public function createForm(): void { Authorization::requirePermission($this->pdo,'STRUCTURE_ADMIN'); $error=$_SESSION['_flash_error']??null; unset($_SESSION['_flash_error']); $view=dirname(__DIR__).'/Views/structures/create.php'; require dirname(__DIR__).'/Views/layout.php'; }
 public function create(): void { Authorization::requirePermission($this->pdo,'STRUCTURE_ADMIN'); Csrf::validate($_POST['_csrf']??null); $c=strtoupper(trim((string)($_POST['code']??''))); $n=trim((string)($_POST['name']??'')); if(!preg_match('/^[A-Z0-9_]{2,100}$/',$c)||$n===''){$_SESSION['_flash_error']='Código o nombre inválido.'; header('Location: /admin/structures/create'); exit;} try{$id=(new ImportStructureRepository($this->pdo))->create($c,$n,trim((string)($_POST['description']??'')),(int)Auth::id()); (new AuditRepository($this->pdo))->log(Auth::id(),'IMPORT_STRUCTURE_CREATED','IMPORT_STRUCTURE',(string)$id,['code'=>$c]); header('Location: /admin/structures/'.$id); exit;}catch(\Throwable $e){error_log($e->getMessage());$_SESSION['_flash_error']='No fue posible crear la estructura.';header('Location: /admin/structures/create');exit;} }
 public function show(int $id): void { Authorization::requirePermission($this->pdo,'STRUCTURE_VIEW'); $r=new ImportStructureRepository($this->pdo); $structure=$r->find($id); if(!$structure){http_response_code(404);exit('Estructura no encontrada.');} $versions=$r->versions($id); $versionFields=[]; foreach($versions as $v)$versionFields[(int)$v['id']]=$r->fields((int)$v['id']); $error=$_SESSION['_flash_error']??null; $success=$_SESSION['_flash_success']??null; unset($_SESSION['_flash_error'],$_SESSION['_flash_success']); $view=dirname(__DIR__).'/Views/structures/show.php'; require dirname(__DIR__).'/Views/layout.php'; }
 public function createVersion(int $sid): void { Authorization::requirePermission($this->pdo,'STRUCTURE_ADMIN'); Csrf::validate($_POST['_csrf']??null); $m=(string)($_POST['target_sheet_mode']??'FIRST_MATCH'); if(!in_array($m,['EXACT','REGEX','FIRST_MATCH'],true))$m='FIRST_MATCH'; $hr=max(1,(int)($_POST['header_row']??1)); $dr=max($hr+1,(int)($_POST['data_start_row']??$hr+1)); $ek=trim((string)($_POST['external_key_field_code']??'')); if($ek===''){$_SESSION['_flash_error']='Debes definir la llave externa.';header('Location: /admin/structures/'.$sid);exit;} try{(new ImportStructureRepository($this->pdo))->createVersion($sid,['target_sheet_mode'=>$m,'target_sheet_value'=>trim((string)($_POST['target_sheet_value']??'')),'header_row'=>$hr,'data_start_row'=>$dr,'external_key_field_code'=>$ek,'allow_csv'=>isset($_POST['allow_csv'])?1:0,'allow_xlsx'=>isset($_POST['allow_xlsx'])?1:0,'notes'=>trim((string)($_POST['notes']??''))],(int)Auth::id());$_SESSION['_flash_success']='Versión creada en borrador.';}catch(\Throwable $e){error_log($e->getMessage());$_SESSION['_flash_error']='No fue posible crear la versión.';} header('Location: /admin/structures/'.$sid);exit; }
 public function createVersionFromExcel(int $sid): void {
  Authorization::requirePermission($this->pdo,'STRUCTURE_ADMIN');
  Csrf::validate($_POST['_csrf']??null);

  try {
   $result=(new StructureVersionBuilder(
    new ImportStructureRepository($this->pdo),
    new \App\Services\Import\ImportFileValidator(),
    new \App\Services\Import\SpreadsheetReader()
   ))->createDraftFromFile(
    $sid,
    is_array($_FILES['structure_file'] ?? null) ? $_FILES['structure_file'] : [],
    (int)Auth::id()
   );

   (new AuditRepository($this->pdo))->log(
    Auth::id(),
    'IMPORT_STRUCTURE_VERSION_CREATED_FROM_FILE',
    'IMPORT_STRUCTURE_VERSION',
    (string)$result['version_id'],
    [
     'structure_id'=>$sid,
     'version_number'=>$result['version_number'],
     'sheet'=>$result['sheet'],
     'headers'=>count($result['headers']),
     'external_key'=>$result['external_key'],
    ]
   );

   $_SESSION['_flash_success']='Borrador creado desde el archivo. Se detectaron '.count($result['headers']).' encabezados en la hoja "'.$result['sheet'].'". Revisa la llave externa antes de activar.';
  } catch(\Throwable $e) {
   error_log($e->getMessage());
   $_SESSION['_flash_error']=$e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible construir la versión desde el archivo.';
  }

  header('Location: /admin/structures/'.$sid);
  exit;
 }

 public function setExternalKey(int $sid,int $vid): void {
  Authorization::requirePermission($this->pdo,'STRUCTURE_ADMIN');
  Csrf::validate($_POST['_csrf']??null);
  $fieldCode=trim((string)($_POST['external_key_field_code']??''));

  try {
   if($fieldCode==='') throw new \RuntimeException('Selecciona el campo que funcionará como llave externa.');
   (new ImportStructureRepository($this->pdo))->setExternalKey($sid,$vid,$fieldCode,(int)Auth::id());
   $_SESSION['_flash_success']='Llave externa actualizada. La versión continúa en borrador.';
  } catch(\Throwable $e) {
   error_log($e->getMessage());
   $_SESSION['_flash_error']=$e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible actualizar la llave externa.';
  }

  header('Location: /admin/structures/'.$sid);
  exit;
 }

 public function addField(int $sid,int $vid): void { Authorization::requirePermission($this->pdo,'STRUCTURE_ADMIN'); Csrf::validate($_POST['_csrf']??null); $fc=trim((string)($_POST['field_code']??'')); $dn=trim((string)($_POST['display_name']??'')); $eh=trim((string)($_POST['excel_header']??'')); $dt=(string)($_POST['data_type']??'STRING'); if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,99}$/',$fc)||$dn===''||$eh===''||!in_array($dt,['STRING','INTEGER','DECIMAL','DATE','DATETIME','BOOLEAN','CATALOG'],true)){$_SESSION['_flash_error']='Configuración de campo inválida.';header('Location: /admin/structures/'.$sid);exit;} try{(new ImportStructureRepository($this->pdo))->addField($vid,['field_code'=>$fc,'display_name'=>$dn,'excel_header'=>$eh,'header_aliases'=>(string)($_POST['header_aliases']??''),'data_type'=>$dt,'is_required'=>isset($_POST['is_required'])?1:0,'is_external_key'=>isset($_POST['is_external_key'])?1:0,'is_reportable'=>isset($_POST['is_reportable'])?1:0,'max_length'=>(int)($_POST['max_length']??0),'validation_regex'=>trim((string)($_POST['validation_regex']??'')),'date_format'=>trim((string)($_POST['date_format']??'')),'sort_order'=>(int)($_POST['sort_order']??0)]);$_SESSION['_flash_success']='Campo agregado.';}catch(\Throwable $e){error_log($e->getMessage());$_SESSION['_flash_error']='No fue posible agregar el campo.';} header('Location: /admin/structures/'.$sid);exit; }
 public function updateFields(int $sid,int $vid): void {
  Authorization::requirePermission($this->pdo,'STRUCTURE_ADMIN');
  Csrf::validate($_POST['_csrf']??null);

  try {
   $fields=$_POST['fields']??[];
   if(!is_array($fields) || $fields===[]) throw new \RuntimeException('No se recibieron campos para actualizar.');
   (new ImportStructureRepository($this->pdo))->updateDraftFields($sid,$vid,$fields);
   $_SESSION['_flash_success']='Configuración de campos guardada.';
  } catch(\Throwable $e) {
   error_log($e->getMessage());
   $_SESSION['_flash_error']=$e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible guardar la configuración de los campos.';
  }

  header('Location: /admin/structures/'.$sid);
  exit;
 }

 public function activate(int $sid,int $vid): void { Authorization::requirePermission($this->pdo,'STRUCTURE_ADMIN'); Csrf::validate($_POST['_csrf']??null); try{(new ImportStructureRepository($this->pdo))->activate($sid,$vid,(int)Auth::id());$_SESSION['_flash_success']='Versión activada.';}catch(\Throwable $e){$_SESSION['_flash_error']=$e->getMessage();} header('Location: /admin/structures/'.$sid);exit; }
}
