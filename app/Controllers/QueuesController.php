<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Auth; use App\Auth\Authorization; use App\Auth\Csrf; use App\Repositories\AuditRepository; use App\Repositories\QueueRepository; use PDO;
final class QueuesController {
 public function __construct(private PDO $pdo) {}
 public function index(): void { Authorization::requirePermission($this->pdo,'QUEUE_VIEW'); $r=new QueueRepository($this->pdo); $queues=$r->all(); $activeVersions=$r->activeVersions(); $agentCapacities=$r->agentsWithCapacity(); $error=$_SESSION['_flash_error']??null; $success=$_SESSION['_flash_success']??null; unset($_SESSION['_flash_error'],$_SESSION['_flash_success']); $view=dirname(__DIR__).'/Views/queues/index.php'; require dirname(__DIR__).'/Views/layout.php'; }
 public function create(): void { Authorization::requirePermission($this->pdo,'QUEUE_ADMIN'); Csrf::validate($_POST['_csrf']??null); $c=strtoupper(trim((string)($_POST['code']??''))); $n=trim((string)($_POST['name']??'')); if(!preg_match('/^[A-Z0-9_]{2,100}$/',$c)||$n===''){$_SESSION['_flash_error']='Código o nombre inválido.';header('Location: /admin/queues');exit;} try{$id=(new QueueRepository($this->pdo))->create(['code'=>$c,'name'=>$n,'description'=>trim((string)($_POST['description']??'')),'default_capacity'=>max(1,(int)($_POST['default_capacity']??1)),'priority'=>(int)($_POST['priority']??100)],(int)Auth::id());(new AuditRepository($this->pdo))->log(Auth::id(),'QUEUE_CREATED','WORK_QUEUE',(string)$id,['code'=>$c]);$_SESSION['_flash_success']='Cola creada.';}catch(\Throwable $e){error_log($e->getMessage());$_SESSION['_flash_error']='No fue posible crear la cola.';} header('Location: /admin/queues');exit; }
 public function updateAgentCapacity(int $queueId,int $userId): void {
  Authorization::requirePermission($this->pdo,'QUEUE_ADMIN');
  Csrf::validate($_POST['_csrf']??null);
  $raw=trim((string)($_POST['capacity']??''));
  $capacity=$raw===''?null:(int)$raw;
  try {
   (new QueueRepository($this->pdo))->updateAgentCapacity(
    $queueId,$userId,$capacity,(int)Auth::id()
   );
   (new AuditRepository($this->pdo))->log(
    Auth::id(),'QUEUE_AGENT_CAPACITY_UPDATED','QUEUE_AGENT',
    $queueId.':'.$userId,
    ['queue_id'=>$queueId,'user_id'=>$userId,'capacity_override'=>$capacity]
   );
   $_SESSION['_flash_success']=$capacity===null
    ? 'Capacidad del agente restablecida a la capacidad de la cola.'
    : 'Capacidad del agente actualizada.';
  } catch(\Throwable $e) {
   error_log('[QueuesController::updateAgentCapacity] '.$e->getMessage());
   $_SESSION['_flash_error']=$e->getMessage();
  }
  header('Location: /admin/queues');
  exit;
 }
 
 public function attach(): void {
  Authorization::requirePermission($this->pdo,'QUEUE_ADMIN');
  Csrf::validate($_POST['_csrf']??null);

  $q=(int)($_POST['queue_id']??0);
  $v=(int)($_POST['structure_version_id']??0);

  if($q<1||$v<1){
    $_SESSION['_flash_error']='Selecciona una cola y una estructura activa.';
    header('Location: /admin/queues');
    exit;
  }

  try {
    (new QueueRepository($this->pdo))->attach($q,$v);
    (new AuditRepository($this->pdo))->log(
      Auth::id(),
      'QUEUE_STRUCTURE_ATTACHED',
      'QUEUE_STRUCTURE',
      $q.':'.$v,
      ['queue_id'=>$q,'structure_version_id'=>$v]
    );
    $_SESSION['_flash_success']='La estructura activa quedó asociada a la cola.';
  } catch(\Throwable $e) {
    error_log('[QueuesController::attach] '.$e->getMessage());
    $_SESSION['_flash_error']=$e instanceof \RuntimeException
      ? $e->getMessage()
      : 'No fue posible asociar la estructura.';
  }

  header('Location: /admin/queues');
  exit;
}
}