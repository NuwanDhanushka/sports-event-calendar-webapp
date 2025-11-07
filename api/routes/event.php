<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\EventController;

/** Routes for managing events */

/** get events by filters or paginate */
$router->get('/event', [EventController::class, 'index']);
/** create event */
$router->post('/event', [EventController::class, 'store']);
/** get event by id */
$router->get('/event/{id}', [EventController::class, 'show']);
/** update event */
$router->put('/event/{id}', [EventController::class, 'update']);
/** delete event */
$router->delete('/event/{id}', [EventController::class, 'destroy']);
/** upload banner */
$router->post('/event/{id}/banner', [EventController::class, 'uploadBanner']);
/** delete banner */
$router->delete('/event/{id}/banner', [EventController::class, 'deleteBanner']);