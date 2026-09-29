<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UsersController;
use App\Http\Router;

return static function (Router $router, PDO $pdo): void {
    $router->get('/', fn() => (new DashboardController($pdo))->index());

    $router->get('/login', fn() => (new AuthController($pdo))->showLogin());
    $router->post('/login', fn() => (new AuthController($pdo))->login());
    $router->post('/logout', fn() => (new AuthController($pdo))->logout());

    $router->get('/admin/users', fn() => (new UsersController($pdo))->index());
    $router->get('/admin/users/create', fn() => (new UsersController($pdo))->createForm());
    $router->post('/admin/users/create', fn() => (new UsersController($pdo))->create());
};
