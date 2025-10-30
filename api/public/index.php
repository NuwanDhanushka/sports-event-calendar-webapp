<?php

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

require __DIR__ . '/../vendor/autoload.php';

$request  = Request::capture();

$router = new Router();
require __DIR__ . '/../routes/api.php';

$match = $router->match($request->method(), $request->path());
if (!$match) {
    (new Response(404, 'Route Not Found', false, []))->send();
}

[$handler, $params] = [$match['handler'], $match['params']];

try {

    $result = is_array($handler)
        ? (new $handler[0]())->{$handler[1]}($request, $params)
        : $handler($request, $params);

    if ($result instanceof Response) {
        $result->send();
    } else {
        (new Response(200, 'OK', true, is_array($result) ? $result : ['data' => $result]))->send();
    }
} catch (\Throwable $e) {
    $data = [];
    if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
        $data['exception'] = get_class($e);
        $data['message']   = $e->getMessage();
    }
    (new Response(500, 'Server Error', false, $data))->send();
}
