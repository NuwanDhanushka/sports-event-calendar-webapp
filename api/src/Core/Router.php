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

    /**
     * Turn a route like "/users/{id}/posts/{slug}" into:
     *   [ '#^/users/([A-Za-z0-9\-_]+)/posts/([A-Za-z0-9\-_]+)$#', ['id', 'slug'] ]
     */
    private function compile(string $path): array
    {
        // 1) Normalize: remove trailing slash but keep "/" as-is
        $path = rtrim($path, '/');
        if ($path === '') $path = '/';

        // 2) Collect placeholder keys: {name}
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $m);
        $keys = $m[1];

        // 3) Escape the whole string for regex (except our braces), then
        //    replace each "{name}" with a capturing group.
        $escaped = preg_quote($path, '#');
        // Replace the *escaped* "{name}" tokens like "\{name\}" with our group
        $regex = preg_replace(
            '/\\\\\{[A-Za-z_][A-Za-z0-9_]*\\\\\}/',
            '([A-Za-z0-9\-_]+)',
            $escaped
        );

        // 4) Anchor and return
        return ['#^' . $regex . '$#', $keys];
    }
}