<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/routes/web.php';
$routes = file_get_contents($path);

if ($routes === false) {
    fwrite(STDERR, "No se pudo leer routes/web.php\n");
    exit(1);
}

if (!str_contains($routes, 'use App\Controllers\AgentPresenceController;')) {
    $anchor = "use App\\Controllers\\ImportsController;\n";
    $insert = $anchor
        . "use App\\Controllers\\AgentPresenceController;\n"
        . "use App\\Controllers\\AgentStatusController;\n";

    if (!str_contains($routes, $anchor)) {
        fwrite(STDERR, "No se encontró el punto de inserción de imports en routes/web.php\n");
        exit(1);
    }

    $routes = str_replace($anchor, $insert, $routes);
}

$routeBlock = <<<'PHP'

 $router->get('/agent/presence',fn()=>(new AgentPresenceController($pdo))->current());
 $router->post('/agent/presence',fn()=>(new AgentPresenceController($pdo))->update());
 $router->post('/agent/heartbeat',fn()=>(new AgentPresenceController($pdo))->heartbeat());

 $router->get('/supervisor/agents',fn()=>(new AgentStatusController($pdo))->index());
 $router->get('/supervisor/agents/data',fn()=>(new AgentStatusController($pdo))->data());
PHP;

if (!str_contains($routes, "/agent/presence")) {
    $lastBrace = strrpos($routes, '};');
    if ($lastBrace === false) {
        fwrite(STDERR, "No se encontró el cierre del archivo de rutas.\n");
        exit(1);
    }

    $routes = substr($routes, 0, $lastBrace)
        . $routeBlock
        . "\n"
        . substr($routes, $lastBrace);
}

if (file_put_contents($path, $routes) === false) {
    fwrite(STDERR, "No se pudo actualizar routes/web.php\n");
    exit(1);
}

echo "Rutas de presencia aplicadas correctamente.\n";
