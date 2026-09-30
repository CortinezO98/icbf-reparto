<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/routes/web.php';
$routes = file_get_contents($path);

if ($routes === false) {
    fwrite(STDERR, "No se pudo leer routes/web.php\n");
    exit(1);
}

$anchor = <<<'PHP'
 $router->post('/admin/users/create',fn()=>(new UsersController($pdo))->create());
PHP;

if (!str_contains($routes, $anchor)) {
    fwrite(STDERR, "No se encontró la ruta base de usuarios.\n");
    fwrite(STDERR, "Verifica que routes/web.php contenga POST /admin/users/create.\n");
    exit(1);
}

$block = <<<'PHP'

 $router->get('/admin/users/{id}/edit',fn(int $id)=>(new UsersController($pdo))->editForm($id));
 $router->post('/admin/users/{id}/edit',fn(int $id)=>(new UsersController($pdo))->update($id));
 $router->post('/admin/users/{id}/toggle-active',fn(int $id)=>(new UsersController($pdo))->toggleActive($id));
PHP;

if (!str_contains($routes, "/admin/users/{id}/edit")) {
    $routes = str_replace($anchor, $anchor . $block, $routes);
}

if (file_put_contents($path, $routes) === false) {
    fwrite(STDERR, "No se pudo actualizar routes/web.php\n");
    exit(1);
}

echo "Rutas de usuarios aplicadas correctamente.\n";
