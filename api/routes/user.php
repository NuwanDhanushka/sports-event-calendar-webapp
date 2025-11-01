<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\UserController;

$router->post('/user',                 [UserController::class, 'store']);            // create user
$router->patch('/user/{id}',           [UserController::class, 'update']);           // edit user
$router->post('/user/{id}/password',   [UserController::class, 'changePassword']);   // change password
$router->post('/user/{id}/deactivate', [UserController::class, 'deactivate']);       // deactivate
