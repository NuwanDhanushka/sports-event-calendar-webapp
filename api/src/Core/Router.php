<?php

namespace App\Core;
class Router{

    private array $routes = [];
    private string $prefix = '/api/v1/';

    public function setPrefix(string $prefix): void {
        $this->prefix = rtrim($prefix, '/');
    }

    public function get(string $path, callable|array $handler): void    { $this->add('GET',    $path, $handler); }
    public function post(string $path, callable|array $handler): void   { $this->add('POST',   $path, $handler); }
    public function put(string $path, callable|array $handler): void    { $this->add('PUT',    $path, $handler); }
    public function patch(string $path, callable|array $handler): void  { $this->add('PATCH',  $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $full = $this->prefix . '/' . ltrim($path, '/');
        [$regex, $keys] = $this->compile($full);
        $this->routes[$method][] = ['regex' => $regex, 'keys' => $keys, 'handler' => $handler];
    }

    public function match(string $method, string $incomingPath): ?array
    {
        $method = strtoupper($method);
        $list = $this->routes[$method] ?? [];
        foreach ($list as $r) {
            if (preg_match($r['regex'], $incomingPath, $m)) {
                $params = [];
                foreach ($r['keys'] as $i => $name) {
                    $params[$name] = $m[$i + 1] ?? null;
                }
                return ['handler' => $r['handler'], 'params' => $params];
            }
        }
        return null;
    }

    private function compile(string $path): array
    {
        $keys = [];
        $regex = preg_replace_callback('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '([A-Za-z0-9\-_]+)';
        }, str_replace('/', '\/', rtrim($path, '/')));

        return ['#^' . $regex . '$#', $keys];
    }

}