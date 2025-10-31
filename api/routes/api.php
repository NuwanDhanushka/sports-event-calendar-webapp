<?php

/** @var \App\Core\Router $router */

$router->setPrefix('/api/v1');

require __DIR__.'/auth.php';
require __DIR__.'/user.php';
require __DIR__.'/event.php';
require __DIR__.'/apiToken.php';
