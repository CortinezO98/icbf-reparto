<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/routes/web.php';
$routes = file_get_contents($path);

if ($routes === false) {
    fwrite(STDERR, "No se pudo leer routes/web.php\n");
    exit(1);
}

if (!str_contains($routes, 'use App\Controllers\CasesController;')) {
    $anchor = <<<'PHP'
use App\Controllers\AgentStatusController;
PHP;

    if (!str_contains($routes, $anchor)) {
        fwrite(STDERR, "No se encontró el punto de inserción de CasesController.\n");
        exit(1);
    }

    $routes = str_replace(
        $anchor,
        $anchor . PHP_EOL . 'use App\Controllers\CasesController;',
        $routes
    );
}

$lines = preg_split('/\R/', $routes) ?: [];
$clean = [];

foreach ($lines as $line) {
    if (
        str_contains($line, "\$router->get('/cases',")
        || str_contains($line, "\$router->get('/cases/{id}',")
        || str_contains($line, "\$router->post('/cases/{id}/manage',")
    ) {
        continue;
    }

    $clean[] = $line;
}

$routes = implode(PHP_EOL, $clean);

$anchor = <<<'PHP'
 $router->get('/admin/structures',fn()=>(new ImportStructuresController($pdo))->index());
PHP;

if (!str_contains($routes, $anchor)) {
    fwrite(STDERR, "No se encontró la ruta /admin/structures para insertar casos.\n");
    exit(1);
}

$block = <<<'PHP'
 $router->get('/cases',fn()=>(new CasesController($pdo))->index());
 $router->get('/cases/{id}',fn(int $id)=>(new CasesController($pdo))->show($id));
 $router->post('/cases/{id}/manage',fn(int $id)=>(new CasesController($pdo))->manage($id));

PHP;

$routes = str_replace($anchor, $block . $anchor, $routes);

if (file_put_contents($path, $routes) === false) {
    fwrite(STDERR, "No se pudo actualizar routes/web.php\n");
    exit(1);
}

$layoutPath = dirname(__DIR__) . '/app/Views/layout.php';
$layout = file_get_contents($layoutPath);

if ($layout === false) {
    fwrite(STDERR, "No se pudo leer app/Views/layout.php\n");
    exit(1);
}

if (!str_contains($layout, 'href="/cases"')) {
    $home = '<a href="/">Inicio</a>';

    if (!str_contains($layout, $home)) {
        fwrite(STDERR, "No se encontró el enlace Inicio en layout.php\n");
        exit(1);
    }

    $layout = str_replace(
        $home,
        $home . PHP_EOL . '                <a href="/cases">Casos</a>',
        $layout,
        $count
    );

    if ($count !== 1 || file_put_contents($layoutPath, $layout) === false) {
        fwrite(STDERR, "No se pudo actualizar la navegación.\n");
        exit(1);
    }
}

echo "Rutas de casos aplicadas correctamente.\n";
