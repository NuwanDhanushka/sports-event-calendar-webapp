<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\EventController;

$router->get('/event', [EventController::class, 'index']);