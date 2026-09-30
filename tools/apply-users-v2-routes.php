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
    fwrite(STDERR, "No se encontró POST /admin/users/create en routes/web.php\n");
    exit(1);
}

$blocks = [
    " $router->get('/admin/users/{id}/edit',fn(int \$id)=>(new UsersController(\$pdo))->editForm(\$id));",
    " $router->post('/admin/users/{id}/edit',fn(int \$id)=>(new UsersController(\$pdo))->update(\$id));",
    " $router->post('/admin/users/{id}/toggle-active',fn(int \$id)=>(new UsersController(\$pdo))->toggleActive(\$id));",
    " $router->get('/admin/users/import',fn()=>(new UsersController(\$pdo))->importForm());",
    " $router->post('/admin/users/import',fn()=>(new UsersController(\$pdo))->importUsers());",
    " $router->get('/admin/users/template',fn()=>(new UsersController(\$pdo))->exportTemplate());",
    " $router->get('/admin/users/export',fn()=>(new UsersController(\$pdo))->exportUsers());",
];

$insert = '';
foreach ($blocks as $route) {
    $pathPart = trim(substr($route, strpos($route, "('") + 2, strpos($route, "',") - (strpos($route, "('") + 2)));
    if (!str_contains($routes, $pathPart)) {
        $insert .= "\n" . $route;
    }
}

if ($insert !== '') {
    $routes = str_replace($anchor, $anchor . $insert, $routes);
}

if (file_put_contents($path, $routes) === false) {
    fwrite(STDERR, "No se pudo actualizar routes/web.php\n");
    exit(1);
}

echo "Rutas de Gestión de Usuarios v2 aplicadas correctamente.\n";
