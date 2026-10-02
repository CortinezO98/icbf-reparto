<?php
ob_start();
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Auth\Auth;
use App\Config\Database;
use App\Http\Router;

Auth::initSession();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $pdo = Database::connection();
        $pdo->query('SELECT 1')->fetchColumn();

        echo json_encode([
            'ok' => true,
            'service' => 'icbf-reparto',
            'database' => 'ok',
            'time' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode([
            'ok' => false,
            'service' => 'icbf-reparto',
            'database' => 'unavailable',
        ]);
    }
    exit;
}

$pdo = Database::connection();

$router = new Router();
$registerRoutes = require dirname(__DIR__) . '/routes/web.php';
$registerRoutes($router, $pdo);
$router->dispatch($method, $path);
