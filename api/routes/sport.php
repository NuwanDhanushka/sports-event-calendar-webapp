<?php
/** @var \App\Core\Router $router */


use App\Http\Controllers\V1\SportController;

/** Routes for managing sports */

/** Get sports */
$router->get('/sport', [SportController::class, 'index']);
