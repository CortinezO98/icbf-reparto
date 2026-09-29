<?php
declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, list<array{pattern:string, handler:callable}>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $quoted = preg_quote($path, '#');
        $pattern = preg_replace_callback(
            '/\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}/',
            static fn(array $matches): string => '([0-9]+)',
            $quoted
        );

        if ($pattern === null) {
            throw new \RuntimeException('No fue posible compilar la ruta.');
        }

        $this->routes[$method][] = [
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            array_shift($matches);
            ($route['handler'])(...array_map('intval', $matches));
            return;
        }

        http_response_code(404);
        echo 'Página no encontrada.';
    }
}
