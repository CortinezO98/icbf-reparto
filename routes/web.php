<?php
declare(strict_types=1);
use App\Controllers\AuthController; use App\Controllers\DashboardController; use App\Controllers\UsersController; use App\Controllers\ImportStructuresController; use App\Controllers\QueuesController; use App\Http\Router;
return static function(Router $router,PDO $pdo): void {
 $router->get('/',fn()=>(new DashboardController($pdo))->index());
 $router->get('/login',fn()=>(new AuthController($pdo))->showLogin()); $router->post('/login',fn()=>(new AuthController($pdo))->login()); $router->post('/logout',fn()=>(new AuthController($pdo))->logout());
 $router->get('/admin/users',fn()=>(new UsersController($pdo))->index()); $router->get('/admin/users/create',fn()=>(new UsersController($pdo))->createForm()); $router->post('/admin/users/create',fn()=>(new UsersController($pdo))->create());
 $router->get('/admin/structures',fn()=>(new ImportStructuresController($pdo))->index()); $router->get('/admin/structures/create',fn()=>(new ImportStructuresController($pdo))->createForm()); $router->post('/admin/structures/create',fn()=>(new ImportStructuresController($pdo))->create()); $router->get('/admin/structures/{id}',fn(int $id)=>(new ImportStructuresController($pdo))->show($id)); $router->post('/admin/structures/{id}/versions/create',fn(int $id)=>(new ImportStructuresController($pdo))->createVersion($id)); $router->post('/admin/structures/{id}/versions/{versionId}/fields/create',fn(int $id,int $versionId)=>(new ImportStructuresController($pdo))->addField($id,$versionId)); $router->post('/admin/structures/{id}/versions/{versionId}/activate',fn(int $id,int $versionId)=>(new ImportStructuresController($pdo))->activate($id,$versionId));
 $router->get('/admin/queues',fn()=>(new QueuesController($pdo))->index()); $router->post('/admin/queues/create',fn()=>(new QueuesController($pdo))->create()); $router->post('/admin/queues/attach-structure',fn()=>(new QueuesController($pdo))->attach());
};
