<?php

/**
 * This is the main entry point for the application.
 * It initializes the environment, loads the routes, and handles the request.
 */

use App\Core\ApiAuth;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

/** autoload the classes */
require __DIR__ . '/../vendor/autoload.php';

/** init the logger */
Logger::init();

/** load the env file */
Env::load(__DIR__ . '/../.env');

/** start the session */
Session::start();

/** capture the request from the global variables */
$request = Request::capture();

/** Check if the request has bearer token */
ApiAuth::requireApiKey($request);

/** Init the router */
$router = new Router();

/** Load the routes */
require __DIR__ . '/../routes/routes.php';

/** match the request with the routes with request method and path */
$match = $router->match($request->method(), $request->path());

/** if no match found, send a 404 */
if (!$match) {
    (new Response(404, 'Route Not Found', false, []))->send();
}

/** extract the handler and params from the match */
[$handler, $params] = [$match['handler'], $match['params']];

try {

    /** Get the result from the handler */
    $result = is_array($handler)
        ? (new $handler[0]())->{$handler[1]}($request, $params)
        : $handler($request, $params);

    /** Check if the result is a Response object */
    if ($result instanceof Response) {

        /** Call send() on the response object to send the response */
        $result->send();
    } else {
        /** If the result is not a Response object, send a 200 OK response with the result as data */
        (new Response(200, 'OK', true, is_array($result) ? $result : ['data' => $result]))->send();
    }
} catch (\Throwable $e) {
    /** Send a 500 Internal Server Error response with the exception class and message */
    $data = [];
    if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
        $data['exception'] = get_class($e);
        $data['message'] = $e->getMessage();
    }
    (new Response(500, 'Server Error', false, $data))->send();
}
