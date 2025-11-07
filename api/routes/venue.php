<?php
/** @var \App\Core\Router $router */


use App\Http\Controllers\V1\VenueController;

/** Routes for managing venues */

/** Get venues */
$router->get('/venue', [VenueController::class, 'index']);