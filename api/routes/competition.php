<?php
/** @var \App\Core\Router $router */


use App\Http\Controllers\V1\CompetitionController;

/** Routes for managing competitions */

/** Get competitions */
$router->get('/competition', [CompetitionController::class, 'index']);

/** Get teams in a competition */
$router->get('/competition/{competitionId}/teams', [CompetitionController::class, 'teams']);