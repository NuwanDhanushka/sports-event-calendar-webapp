<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\AuthController;

/** Routes for authentication */

/** login a user */
$router->post('/auth/login', [AuthController::class, 'login']);
/** get current login user */
$router->get('/auth/me', [AuthController::class, 'me']);
/** logout a user */
$router->post('/auth/logout', [AuthController::class, 'logout']);