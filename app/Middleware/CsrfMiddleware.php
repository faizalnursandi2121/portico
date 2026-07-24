<?php

namespace App\Middleware;

use App\Helpers\CsrfHelper;
use App\Helpers\ErrorHelper;

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (! CsrfHelper::validate($token)) {
            ErrorHelper::show(403, 'Forbidden', 'CSRF token validation failed.');
        }

        return $next($request);
    }
}
