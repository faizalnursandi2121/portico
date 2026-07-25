<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

ini_set('session.save_path', '/tmp');
\App\Core\Session::start();

$payload = '<img src=x onerror=alert(1)>';
$code = 500;
$message = $payload;
$description = $payload;
$_SERVER['REQUEST_URI'] = '/error';

ob_start();
require ROOT.'/app/Views/errors/default.php';
$renderedError = ob_get_clean();

if (str_contains($renderedError, $payload)
    || str_contains($renderedError, '<img src=x')
    || preg_match('/<title>[^<]*<img/i', $renderedError)) {
    throw new RuntimeException('FAIL: error view rendered hostile HTML');
}

$session = 'fixture';
ob_start();
require ROOT.'/app/Views/public/status.php';
$renderedStatus = ob_get_clean();

if (! str_contains($renderedStatus, 'status-renderer.js')
    || ! str_contains($renderedStatus, 'renderStatusDetails')) {
    throw new RuntimeException('FAIL: public status view is not wired to the safe renderer');
}

$currentTemplate = 'default';
$templates = [];
$users = [[
    'username' => $payload,
    'password' => $payload,
    'price' => $payload,
    'validity' => $payload,
    'timelimit' => $payload,
    'datalimit' => $payload,
    'profile' => $payload,
    'comment' => $payload,
    'hotspotname' => $payload,
    'dns_name' => $payload,
    'login_url' => 'https://router.example/login',
]];
$templateContent = '<div onmouseover="window.__porticoTemplateXss=1">{{username}}</div><script>window.__porticoTemplateXss=1</script>';
$logoMap = [];
$_SERVER['REQUEST_URI'] = '/print';

ob_start();
require ROOT.'/app/Views/print/custom.php';
$renderedPrint = ob_get_clean();

if (str_contains($renderedPrint, $payload)
    || str_contains($renderedPrint, '<img src=x')
    || str_contains($renderedPrint, 'window.__porticoTemplateXss')
    || str_contains($renderedPrint, 'onmouseover=')) {
    throw new RuntimeException('FAIL: custom print view rendered hostile voucher data or template');
}

$templateContent = '<section class="safe-template">{{username}}</section>';
ob_start();
require ROOT.'/app/Views/print/custom.php';
$renderedSafeTemplate = ob_get_clean();

if (! str_contains($renderedSafeTemplate, 'class="safe-template"')
    || str_contains($renderedSafeTemplate, $payload)
    || ! str_contains($renderedSafeTemplate, '&lt;img src=x onerror=alert(1)&gt;')) {
    throw new RuntimeException('FAIL: safe custom template was not preserved with escaped voucher data');
}

$jsPayload = '";</script><script>window.__porticoQrXss=1</script>//';
$users[0]['username'] = $jsPayload;
$users[0]['password'] = $jsPayload;
$templateContent = '<div class="safe-qr-template">{{qrcode}}</div>';
ob_start();
require ROOT.'/app/Views/print/custom.php';
$renderedQrTemplate = ob_get_clean();
$qrValue = $users[0]['login_url'].'?user='.$jsPayload.'&password='.$jsPayload;
$encodedQrValue = json_encode($qrValue, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

if (! str_contains($renderedQrTemplate, 'value: '.$encodedQrValue)
    || str_contains($renderedQrTemplate, '</script><script>window.__porticoQrXss=1')) {
    throw new RuntimeException('FAIL: QR code JavaScript value was not safely serialized');
}

\App\Core\Session::destroy();

echo "PASS: XSS escaping checks\n";
