<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Session;
use App\Core\Router;
use App\Helpers\CsrfHelper;
use App\Middleware\CsrfMiddleware;

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('FAIL: '.$message);
    }
}

ini_set('session.save_path', '/tmp');
Session::start();

$token = CsrfHelper::token();
assertTrue(strlen($token) === 64, 'CSRF token must be 32 random bytes encoded as hex');
assertTrue(CsrfHelper::validate($token), 'issued CSRF token must validate');
assertTrue(! CsrfHelper::validate(str_repeat('0', 64)), 'unknown CSRF token must fail validation');
$rotatedToken = CsrfHelper::rotate();
assertTrue($rotatedToken !== $token, 'CSRF token must rotate after authentication');
$token = $rotatedToken;

$postMarkup = CsrfHelper::injectFields('<form action="/save" method="POST"><button>Save</button></form>');
assertTrue(substr_count($postMarkup, 'name="_csrf"') === 1, 'POST forms must receive one CSRF field');

$getMarkup = CsrfHelper::injectFields('<form action="/search" method="GET"><button>Search</button></form>');
assertTrue(! str_contains($getMarkup, 'name="_csrf"'), 'GET forms must not receive CSRF fields');

$_POST['_csrf'] = $token;
$middlewareResult = (new CsrfMiddleware)->handle($_SERVER, fn () => 'allowed');
assertTrue($middlewareResult === 'allowed', 'valid CSRF tokens must pass the middleware');
unset($_POST['_csrf']);

$router = new Router;
$router->post('/save', fn () => null);
$router->post('/api/save', fn () => null);
$routesProperty = new ReflectionProperty($router, 'routes');
$routes = $routesProperty->getValue($router);
assertTrue(in_array('csrf', $routes['POST']['/save']['middleware'], true), 'web POST routes must receive CSRF middleware');
assertTrue(! in_array('csrf', $routes['POST']['/api/save']['middleware'], true), 'API routes must retain their existing contract');

Session::destroy();

echo "PASS: Batch 1B CSRF checks\n";
