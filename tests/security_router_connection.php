<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Libraries\RouterOSAPI;

if (! method_exists(RouterOSAPI::class, 'openSocket')) {
    throw new RuntimeException('FAIL: shared RouterOS connection boundary is not testable');
}

class CapturingRouterOSAPI extends RouterOSAPI
{
    public ?string $capturedEndpoint = null;

    protected function openSocket(string $endpoint, $context)
    {
        $this->capturedEndpoint = $endpoint;

        return false;
    }
}

$ipv6Api = new CapturingRouterOSAPI;
$ipv6Api->attempts = 1;
$ipv6Api->delay = 0;
$ipv6Api->connect('fc00::1', 'user', 'password');
if ($ipv6Api->capturedEndpoint !== '[fc00::1]:8728') {
    throw new RuntimeException('FAIL: IPv6 RouterOS endpoint must use brackets');
}

$invalidApi = new CapturingRouterOSAPI;
$invalidApi->attempts = 1;
$invalidApi->delay = 0;
$invalidApi->connect('127.0.0.1', 'user', 'password');
if ($invalidApi->capturedEndpoint !== null || $invalidApi->error_str !== 'Invalid router target') {
    throw new RuntimeException('FAIL: shared RouterOS boundary accepted loopback');
}

echo "PASS: shared RouterOS connection boundary checks\n";
