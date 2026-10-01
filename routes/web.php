<?php
declare(strict_types=1);
use App\Controllers\AuthController;
use App\Controllers\UsersController;
use App\Controllers\ImportStructuresController;
use App\Controllers\QueuesController;
use App\Controllers\ImportsController;
use App\Controllers\AgentPresenceController;
use App\Controllers\AgentStatusController;
use App\Controllers\CasesController;
use App\Controllers\SlaController;
use App\Controllers\ReportsController;
use App\Controllers\ShiftController;
use App\Auth\Auth;
use App\Auth\Authorization;
use App\Http\Router;

return static function(Router $router,PDO $pdo): void {
 $landing = static function() use ($pdo): void {
  Auth::requireLogin();
  $uid = (int)(Auth::id() ?? 0);
  if (Authorization::hasPermission($pdo, $uid, 'SLA_VIEW')) {
   (new SlaController($pdo))->index();
   return;
  }
  (new CasesController($pdo))->index();
 };
 $router->get('/', $landing);
 $router->get('/dashboard', $landing);
 $router->get('/login',fn()=>(new AuthController($pdo))->showLogin());
 $router->post('/login',fn()=>(new AuthController($pdo))->login());
 $router->post('/logout',fn()=>(new AuthController($pdo))->logout());

 $router->get('/admin/users',fn()=>(new UsersController($pdo))->index());
 $router->get('/admin/users/create',fn()=>(new UsersController($pdo))->createForm());
 $router->post('/admin/users/create',fn()=>(new UsersController($pdo))->create());
 $router->get('/admin/users/{id}/edit',fn(int $id)=>(new UsersController($pdo))->editForm($id));
 $router->post('/admin/users/{id}/edit',fn(int $id)=>(new UsersController($pdo))->update($id));
 $router->post('/admin/users/{id}/toggle-active',fn(int $id)=>(new UsersController($pdo))->toggleActive($id));
 $router->get('/admin/users/import',fn()=>(new UsersController($pdo))->importForm());
 $router->post('/admin/users/import',fn()=>(new UsersController($pdo))->importUsers());
 $router->get('/admin/users/template',fn()=>(new UsersController($pdo))->exportTemplate());
 $router->get('/admin/users/export',fn()=>(new UsersController($pdo))->exportUsers());

 $router->get('/sla',fn()=>(new SlaController($pdo))->index());
 $router->get('/reports',fn()=>(new ReportsController($pdo))->index());
 $router->get('/reports/export',fn()=>(new ReportsController($pdo))->export());
 $router->get('/cases',fn()=>(new CasesController($pdo))->index());
 $router->get('/cases/{id}',fn(int $id)=>(new CasesController($pdo))->show($id));
 $router->post('/cases/{id}/manage',fn(int $id)=>(new CasesController($pdo))->manage($id));
 $router->get('/cases/{id}/reassignment-options',fn(int $id)=>(new CasesController($pdo))->reassignmentOptions($id));
 $router->post('/cases/{id}/reassign',fn(int $id)=>(new CasesController($pdo))->reassign($id));

 $router->get('/admin/structures',fn()=>(new ImportStructuresController($pdo))->index());
 $router->get('/admin/structures/create',fn()=>(new ImportStructuresController($pdo))->createForm());
 $router->post('/admin/structures/create',fn()=>(new ImportStructuresController($pdo))->create());
 $router->get('/admin/structures/{id}',fn(int $id)=>(new ImportStructuresController($pdo))->show($id));
 $router->post('/admin/structures/{id}/versions/create',fn(int $id)=>(new ImportStructuresController($pdo))->createVersion($id));
 $router->post('/admin/structures/{id}/versions/{versionId}/fields/create',fn(int $id,int $versionId)=>(new ImportStructuresController($pdo))->addField($id,$versionId));
 $router->post('/admin/structures/{id}/versions/{versionId}/activate',fn(int $id,int $versionId)=>(new ImportStructuresController($pdo))->activate($id,$versionId));

 $router->get('/admin/queues',fn()=>(new QueuesController($pdo))->index());
 $router->post('/admin/queues/create',fn()=>(new QueuesController($pdo))->create());
 $router->post('/admin/queues/attach-structure',fn()=>(new QueuesController($pdo))->attach());
 $router->post('/admin/queues/{queueId}/agents/{userId}/capacity',fn(int $queueId,int $userId)=>(new QueuesController($pdo))->updateAgentCapacity($queueId,$userId));

 $router->get('/admin/shifts',fn()=>(new ShiftController($pdo))->index());
 $router->post('/admin/shifts/create',fn()=>(new ShiftController($pdo))->createShift());
 $router->post('/admin/shifts/schedules/create',fn()=>(new ShiftController($pdo))->createSchedule());
 $router->post('/admin/shifts/schedules/{id}/toggle',fn(int $id)=>(new ShiftController($pdo))->toggleSchedule($id));

 $router->get('/imports',fn()=>(new ImportsController($pdo))->index());
 $router->post('/imports/upload',fn()=>(new ImportsController($pdo))->upload());
 $router->get('/imports/{id}',fn(int $id)=>(new ImportsController($pdo))->show($id));
 $router->post('/imports/{id}/confirm',fn(int $id)=>(new ImportsController($pdo))->confirm($id));

 $router->get('/agent/presence',fn()=>(new AgentPresenceController($pdo))->current());
 $router->post('/agent/presence',fn()=>(new AgentPresenceController($pdo))->update());
 $router->post('/agent/heartbeat',fn()=>(new AgentPresenceController($pdo))->heartbeat());

 $router->get('/supervisor/agents',fn()=>(new AgentStatusController($pdo))->index());
 $router->get('/supervisor/agents/data',fn()=>(new AgentStatusController($pdo))->data());
};