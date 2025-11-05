<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\ApiTokenController;

/** Routes for managing API tokens */

/** create a new app key */
$router->post('/api-tokens', [ApiTokenController::class, 'store']);
/** list all app keys */
$router->get('/api-tokens', [ApiTokenController::class, 'index']);
/** delete a key */
$router->delete('/api-tokens/{id}', [ApiTokenController::class, 'destroy']); // delete a key
