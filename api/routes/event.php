<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\EventController;

$router->get('/event',        [EventController::class, 'index']);
$router->post('/event',       [EventController::class, 'store']);
$router->get('/event/{id}',   [EventController::class, 'show']);
$router->put('/event/{id}',   [EventController::class, 'update']);
$router->delete('/event/{id}',[EventController::class, 'destroy']);
$router->post('/event/{id}/banner',   [EventController::class, 'uploadBanner']);
$router->delete('/event/{id}/banner', [EventController::class, 'deleteBanner']);