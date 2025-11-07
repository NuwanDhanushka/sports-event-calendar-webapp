<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\UserController;

/** Routes for managing users */

/** get users' list */
$router->post('/user', [UserController::class, 'store']);
/** update user*/
$router->patch('/user/{id}', [UserController::class, 'update']);
/** change password */
$router->post('/user/{id}/password', [UserController::class, 'changePassword']);
/** deactivate user */
$router->post('/user/{id}/deactivate', [UserController::class, 'deactivate']);
