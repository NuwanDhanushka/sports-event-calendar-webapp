<?php

namespace App\Core;
/**
 * Router class
 * Handles routing
 * Dynamically maps incoming requests to controllers
 * regex-based HTTP router with path params like {id}.
 *  Example:
 *    $r->setPrefix('/api/v1');
 *    $r->get('/users/{id}', [UserController::class,'show']);
 *    $match = $r->match($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
 *
 */
class Router{

    /** array of routes */
    private array $routes = [];

    /** Prefix for all routes */
    private string $prefix = '/api/v1/';

    /**
     * Set the prefix for all routes
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void {
        $this->prefix = rtrim($prefix, '/');
    }

    /** Http methods to register routes */
    public function get(string $path, callable|array $handler): void    { $this->add('GET',    $path, $handler); }
    public function post(string $path, callable|array $handler): void   { $this->add('POST',   $path, $handler); }
    public function put(string $path, callable|array $handler): void    { $this->add('PUT',    $path, $handler); }
    public function patch(string $path, callable|array $handler): void  { $this->add('PATCH',  $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }

    /**
     * Register a route compile path with {params} into a regex  capture keys.
     * @param string $method
     * @param string $path
     * @param callable|array $handler
     * @return void
     */
    private function add(string $method, string $path, callable|array $handler): void
    {
        /** Ensure exactly one slash between prefix and path */
        $full = $this->prefix . '/' . ltrim($path, '/');

        /** Compile the path into a regex
         * e.g. '/users/{id}' => ['#^/users/([A-Za-z0-9\-_]+)$#', ['id']]
         * */
        [$regex, $keys] = $this->compile($full);

        /** Store route under its HTTP method */
        $this->routes[$method][] = ['regex' => $regex, 'keys' => $keys, 'handler' => $handler];
    }

    /**
     * Match a route to an incoming request its match method and path.
     * Returns the handler and params if a match is found, null otherwise.
     * @param string $method
     * @param string $incomingPath
     * @return array|null
     */
    public function match(string $method, string $incomingPath): ?array
    {
        $method = strtoupper($method);
        $list = $this->routes[$method] ?? [];
        foreach ($list as $row) {
            /** Match the route regex against the incoming path */
            if (preg_match($row['regex'], $incomingPath, $match)) {
                /** Extract params from regex matches */
                $params = [];
                foreach ($row['keys'] as $i => $name) {
                    $params[$name] = $match[$i + 1] ?? null; // +1 to skip full match
                }
                return ['handler' => $row['handler'], 'params' => $params];
            }
        }
        return null;
    }

    /**
     * Compile a path with {placeholders} into a regex + list of keys.
     * "/users/{id}/posts/{slug}" =>
     *   ['#^/users/([A-Za-z0-9\-_]+)/posts/([A-Za-z0-9\-_]+)$#', ['id','slug']]
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