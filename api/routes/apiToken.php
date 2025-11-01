<?php
/** @var \App\Core\Router $router */

use App\Http\Controllers\V1\ApiTokenController;

$router->post   ('/api-tokens',        [ApiTokenController::class, 'store']);   // create a new app key
$router->get    ('/api-tokens',        [ApiTokenController::class, 'index']);   // list keys
$router->delete ('/api-tokens/{id}',   [ApiTokenController::class, 'destroy']); // delete a key
