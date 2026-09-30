<?php
declare(strict_types=1);

$routePath = dirname(__DIR__) . '/routes/web.php';
$routes = file_get_contents($routePath);

if ($routes === false) {
    fwrite(STDERR, "No se pudo leer routes/web.php\n");
    exit(1);
}

if (!str_contains($routes, 'use App\Controllers\CasesController;')) {
    $anchor = "use App\\Controllers\\UsersController;\n";
    if (!str_contains($routes, $anchor)) {
        fwrite(STDERR, "No se encontró el punto de inserción de Controllers.\n");
        exit(1);
    }
    $routes = str_replace(
        $anchor,
        $anchor . "use App\\Controllers\\CasesController;\n",
        $routes
    );
}

$marker = " $router->get('/admin/structures'";
$block = <<<'PHP'

 $router->get('/cases',fn()=>(new CasesController($pdo))->index());
 $router->get('/cases/{id}',fn(int $id)=>(new CasesController($pdo))->show($id));
 $router->post('/cases/{id}/manage',fn(int $id)=>(new CasesController($pdo))->manage($id));

PHP;

if (!str_contains($routes, "('/cases'")) {
    $pos = strpos($routes, $marker);
    if ($pos === false) {
        fwrite(STDERR, "No se encontró punto de inserción de rutas.\n");
        exit(1);
    }
    $routes = substr($routes, 0, $pos) . $block . substr($routes, $pos);
}

file_put_contents($routePath, $routes);

$layoutPath = dirname(__DIR__) . '/app/Views/layout.php';
$layout = file_get_contents($layoutPath);

if ($layout !== false && !str_contains($layout, 'href="/cases"')) {
    $layout = str_replace(
        '<a href="/">Inicio</a>',
        '<a href="/">Inicio</a>' . PHP_EOL . '                <a href="/cases">Casos</a>',
        $layout
    );
    file_put_contents($layoutPath, $layout);
}

echo "Rutas y acceso de casos aplicados correctamente.\n";
