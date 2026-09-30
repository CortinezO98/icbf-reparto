<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/routes/web.php';
$routes = file_get_contents($path);

if ($routes === false) {
    fwrite(STDERR, "No se pudo leer routes/web.php\n");
    exit(1);
}

if (!str_contains($routes, 'use App\Controllers\SlaController;')) {
    $anchor = <<<'PHP'
use App\Controllers\CasesController;
PHP;

    if (!str_contains($routes, $anchor)) {
        fwrite(STDERR, "No se encontró CasesController en routes/web.php\n");
        exit(1);
    }

    $routes = str_replace(
        $anchor,
        $anchor . PHP_EOL . 'use App\Controllers\SlaController;',
        $routes
    );
}

if (!str_contains($routes, "('/sla'")) {
    $anchor = <<<'PHP'
 $router->get('/cases',fn()=>(new CasesController($pdo))->index());
PHP;

    if (!str_contains($routes, $anchor)) {
        fwrite(STDERR, "No se encontró la ruta /cases.\n");
        exit(1);
    }

    $route = <<<'PHP'
 $router->get('/sla',fn()=>(new SlaController($pdo))->index());

PHP;

    $routes = str_replace($anchor, $route . $anchor, $routes);
}

if (file_put_contents($path, $routes) === false) {
    fwrite(STDERR, "No se pudo actualizar routes/web.php\n");
    exit(1);
}

echo "Ruta /sla aplicada correctamente.\n";
