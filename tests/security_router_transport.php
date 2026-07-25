<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Libraries\RouterOSAPI;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            'FAIL: %s (expected %s, got %s)',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

class TransportCaptureAPI extends RouterOSAPI
{
    public array $endpoints = [];

    public array $contexts = [];

    public array $warnings = [];

    protected function openSocket(string $endpoint, $context)
    {
        $this->endpoints[] = $endpoint;
        $this->contexts[] = stream_context_get_options($context);

        return false;
    }

    protected function logSecurityWarning(string $message): void
    {
        $this->warnings[] = $message;
    }
}

putenv('APP_ENV=production');
putenv('ROUTEROS_TLS=true');
putenv('ROUTEROS_TLS_CA_FILE=/tmp/portico-router-ca.pem');
putenv('ROUTEROS_ALLOW_INSECURE=false');

$tls = new TransportCaptureAPI;
$tls->attempts = 2;
$tls->delay = 0;
$tls->connect('192.168.88.1', 'router-user', 'router-password', 'router.internal.example');

assertSameValue(
    ['tls://192.168.88.1:8729', 'tls://192.168.88.1:8729'],
    $tls->endpoints,
    'TLS must use port 8729 and must not downgrade after failure'
);
$sslOptions = $tls->contexts[0]['ssl'] ?? [];
assertSameValue(true, $sslOptions['verify_peer'] ?? null, 'TLS must verify the peer certificate');
assertSameValue(true, $sslOptions['verify_peer_name'] ?? null, 'TLS must verify the peer name');
assertSameValue(false, $sslOptions['allow_self_signed'] ?? null, 'TLS must reject self-signed certificates');
assertSameValue('router.internal.example', $sslOptions['peer_name'] ?? null, 'TLS must use the configured router hostname as peer name');
assertSameValue(true, $sslOptions['SNI_enabled'] ?? null, 'TLS must send the configured peer name through SNI');
assertSameValue('/tmp/portico-router-ca.pem', $sslOptions['cafile'] ?? null, 'TLS must pass the configured CA file');

$explicitPort = new TransportCaptureAPI;
$explicitPort->attempts = 1;
$explicitPort->delay = 0;
$explicitPort->port = 9443;
$explicitPort->connect('10.0.0.1', 'user', 'password');
assertSameValue(['tls://10.0.0.1:9443'], $explicitPort->endpoints, 'explicit TLS port must be preserved');

putenv('ROUTEROS_TLS=false');
putenv('ROUTEROS_TLS_CA_FILE=');
putenv('ROUTEROS_ALLOW_INSECURE=false');

$deniedPlaintext = new TransportCaptureAPI;
$deniedPlaintext->attempts = 1;
$deniedPlaintext->delay = 0;
assertSameValue(false, $deniedPlaintext->connect('10.0.0.1', 'user', 'password'), 'production plaintext must fail closed');
assertSameValue([], $deniedPlaintext->endpoints, 'denied plaintext must not open a socket');

putenv('ROUTEROS_ALLOW_INSECURE=true');
$allowedPlaintext = new TransportCaptureAPI;
$allowedPlaintext->attempts = 1;
$allowedPlaintext->delay = 0;
$allowedPlaintext->connect('10.0.0.1', 'sensitive-user', 'sensitive-password');
assertSameValue(['10.0.0.1:8728'], $allowedPlaintext->endpoints, 'explicit plaintext override must use port 8728');
assertSameValue(1, count($allowedPlaintext->warnings), 'plaintext override must emit one security warning');
if (str_contains($allowedPlaintext->warnings[0], 'sensitive-user')
    || str_contains($allowedPlaintext->warnings[0], 'sensitive-password')) {
    throw new RuntimeException('FAIL: plaintext warning exposed credentials');
}

putenv('APP_ENV');
putenv('ROUTEROS_TLS');
putenv('ROUTEROS_TLS_CA_FILE');
putenv('ROUTEROS_ALLOW_INSECURE');

echo "PASS: RouterOS TLS transport checks\n";
