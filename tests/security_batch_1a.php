<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Config\SiteConfig;
use App\Core\Session;

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('FAIL: '.$message);
    }
}

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

putenv('APP_ENV=production');
putenv('APP_DEBUG=true');
assertSameValue(false, SiteConfig::isDebugEnabled(), 'production must disable debug even when APP_DEBUG is true');

putenv('APP_ENV=development');
putenv('APP_DEBUG=true');
assertSameValue(true, SiteConfig::isDebugEnabled(), 'development debug flag should enable debug');

putenv('APP_DEBUG=not-a-boolean');
assertSameValue(false, SiteConfig::isDebugEnabled(), 'invalid APP_DEBUG must fail closed');

putenv('APP_KEY=');
$missingKeyRejected = false;
try {
    SiteConfig::getSecretKey();
} catch (RuntimeException $exception) {
    $missingKeyRejected = true;
}
assertTrue($missingKeyRejected, 'missing APP_KEY must be rejected');

$applicationKey = str_repeat('a', 32);
putenv('APP_KEY='.$applicationKey);
assertSameValue($applicationKey, SiteConfig::getSecretKey(), 'configured APP_KEY should be returned unchanged');

$_SERVER['HTTPS'] = 'on';
Session::configure();
$cookieParams = session_get_cookie_params();
assertSameValue(true, $cookieParams['secure'], 'HTTPS requests must use secure session cookies');
assertSameValue(true, $cookieParams['httponly'], 'session cookies must be HttpOnly');
assertSameValue('Lax', $cookieParams['samesite'], 'session cookies must use SameSite=Lax');
assertSameValue('1', ini_get('session.use_strict_mode'), 'strict session mode must be enabled');
assertSameValue('1', ini_get('session.use_only_cookies'), 'cookie-only sessions must be enabled');

ini_set('session.save_path', '/tmp');
Session::start();
$_SESSION['batch_1a_probe'] = 'preserved';
$sessionIdBeforeRegeneration = session_id();
Session::regenerateId();
assertTrue(session_id() !== $sessionIdBeforeRegeneration, 'login session ID must be regenerated');
assertSameValue('preserved', $_SESSION['batch_1a_probe'], 'session data must survive ID regeneration');
Session::destroy();
assertTrue($_SESSION === [], 'logout must clear session data');

unset($_SERVER['HTTPS']);
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);

echo "PASS: Batch 1A security checks\n";
