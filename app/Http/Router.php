<?php
declare(strict_types=1);
namespace App\Http;
final class Router {
 private array $routes=[];
 public function get(string $path, callable $handler): void {$this->add('GET',$path,$handler);} public function post(string $path, callable $handler): void {$this->add('POST',$path,$handler);}
 private function add(string $method,string $path,callable $handler): void { $names=[]; $q=preg_quote($path,'#'); $pattern=preg_replace_callback('/\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}/',function($m)use(&$names){$names[]=$m[1];return '([0-9]+)';},$q); $this->routes[$method][]=['pattern'=>'#^'.$pattern.'$#','handler'=>$handler]; }
 public function dispatch(string $method,string $path): void { foreach($this->routes[$method]??[] as $r){ if(preg_match($r['pattern'],$path,$m)){array_shift($m);($r['handler'])(...array_map('intval',$m));return;}} http_response_code(404);echo 'Página no encontrada.'; }
}
