<?php
/** @var \App\Core\Router $router */


use App\Http\Controllers\V1\SportController;

$router->get('/sport',        [SportController::class, 'index']);
$router->get('/sport/{id}',   [SportController::class, 'show']);
