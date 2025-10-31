<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\AuthController;

$router->post('/auth/login',        [AuthController::class, 'login']);
$router->get('/auth/me',       [AuthController::class, 'me']);
$router->post('/auth/logout',   [AuthController::class, 'logout']);