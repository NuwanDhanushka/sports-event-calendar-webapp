<?php

/** @var \App\Core\Router $router */

$router->setPrefix('/api/v1');

/**
 * All Routes files should be defined here
 */
require __DIR__.'/auth.php';
require __DIR__.'/user.php';
require __DIR__.'/event.php';
require __DIR__.'/sport.php';
require __DIR__.'/apiToken.php';
