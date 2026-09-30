<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/routes/web.php';
$routes = file_get_contents($path);

if ($routes === false) {
    fwrite(STDERR, "No se pudo leer routes/web.php\n");
    exit(1);
}

/*
 * Remove malformed lines left by the previous users-v2 route installer.
 * Keep all valid $router->... lines and unrelated routes intact.
 */
$lines = preg_split('/\R/', $routes) ?: [];
$clean = [];

foreach ($lines as $line) {
    $trim = trim($line);

    $isUsersV2Path =
        str_contains($trim, '/admin/users/{id}/edit')
        || str_contains($trim, '/admin/users/{id}/toggle-active')
        || str_contains($trim, '/admin/users/import')
        || str_contains($trim, '/admin/users/template')
        || str_contains($trim, '/admin/users/export');

    if ($isUsersV2Path) {
        // Remove all current copies, valid or malformed; canonical copies are reinserted below.
        continue;
    }

    $clean[] = $line;
}

$routes = implode(PHP_EOL, $clean);

$anchor = <<<'PHP'
 $router->post('/admin/users/create',fn()=>(new UsersController($pdo))->create());
PHP;

if (!str_contains($routes, $anchor)) {
    fwrite(STDERR, "No se encontró POST /admin/users/create en routes/web.php\n");
    exit(1);
}

$block = <<<'PHP'

 $router->get('/admin/users/{id}/edit',fn(int $id)=>(new UsersController($pdo))->editForm($id));
 $router->post('/admin/users/{id}/edit',fn(int $id)=>(new UsersController($pdo))->update($id));
 $router->post('/admin/users/{id}/toggle-active',fn(int $id)=>(new UsersController($pdo))->toggleActive($id));
 $router->get('/admin/users/import',fn()=>(new UsersController($pdo))->importForm());
 $router->post('/admin/users/import',fn()=>(new UsersController($pdo))->importUsers());
 $router->get('/admin/users/template',fn()=>(new UsersController($pdo))->exportTemplate());
 $router->get('/admin/users/export',fn()=>(new UsersController($pdo))->exportUsers());
PHP;

$routes = str_replace($anchor, $anchor . $block, $routes);

if (file_put_contents($path, $routes) === false) {
    fwrite(STDERR, "No se pudo actualizar routes/web.php\n");
    exit(1);
}

echo "routes/web.php reparado y rutas de Usuarios v2 aplicadas correctamente.\n";
