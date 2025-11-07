<?php
/** @var \App\Core\Router $router */


use App\Http\Controllers\V1\TeamController;

/** Routes for managing teams */

/** Get teams */
$router->get('/team', [TeamController::class, 'index']);