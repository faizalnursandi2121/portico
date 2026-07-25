<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Router;

$router = new Router;
require ROOT.'/routes/api.php';

$routesProperty = new ReflectionProperty($router, 'routes');
$routes = $routesProperty->getValue($router);
$interfaceMiddleware = $routes['POST']['/api/router/interfaces']['middleware'] ?? [];
$statusMiddleware = $routes['POST']['/api/status/check']['middleware'] ?? [];

if (! in_array('auth', $interfaceMiddleware, true)) {
    throw new RuntimeException('FAIL: router interface API must require authentication');
}

if (in_array('auth', $statusMiddleware, true)) {
    throw new RuntimeException('FAIL: public status API contract must remain public');
}

echo "PASS: API route authorization checks\n";
