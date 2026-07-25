<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Helpers\RouterTargetHelper;

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

if (! class_exists(RouterTargetHelper::class)) {
    throw new RuntimeException('FAIL: RouterTargetHelper does not exist');
}

$fixtures = [
    'router.internal.example' => [['type' => 'A', 'ip' => '192.168.88.1']],
    'router-v6.internal.example' => [['type' => 'AAAA', 'ipv6' => 'fc00::1']],
    'router-mixed.internal.example' => [
        ['type' => 'A', 'ip' => '10.0.0.1'],
        ['type' => 'AAAA', 'ipv6' => '::1'],
    ],
    'router-missing.internal.example' => [],
];
$resolver = static fn (string $host): array => $fixtures[$host] ?? [];
$failedResolver = static fn (string $host): false => false;

$cases = [
    ['192.168.88.1', true],
    ['10.0.0.1', true],
    ['fc00::1', true],
    ['router.internal.example', true],
    ['router-v6.internal.example', true],
    ['router-missing.internal.example', false],
    ['router-mixed.internal.example', false],
    ['127.0.0.1', false],
    ['::1', false],
    ['0.0.0.0', false],
    ['::', false],
    ['fe80::1', false],
    ['ff02::1', false],
    ['::ffff:127.0.0.1', false],
    ['::127.0.0.1', false],
    ['::169.254.169.254', false],
    ['169.254.169.254', false],
    ['224.0.0.1', false],
    ['http://example.com', false],
    ['file:///etc/passwd', false],
    ['user@example.com', false],
    ['router.example/path', false],
    ['router.example?port=8728', false],
    ['router.example#fragment', false],
];

foreach ($cases as [$target, $expected]) {
    try {
        RouterTargetHelper::resolve($target, $resolver);
        $actual = true;
    } catch (InvalidArgumentException) {
        $actual = false;
    }

    assertSameValue($expected, $actual, 'router target '.$target);
    assertSameValue($expected, RouterTargetHelper::validate($target, $resolver), 'boolean validation for '.$target);
}

assertSameValue(
    '192.168.88.1',
    RouterTargetHelper::resolve('router.internal.example', $resolver),
    'hostname must resolve to its validated IPv4 address'
);
assertSameValue(
    'fc00::1',
    RouterTargetHelper::resolve('router-v6.internal.example', $resolver),
    'hostname must resolve to its validated IPv6 address'
);
assertSameValue(
    false,
    RouterTargetHelper::validate('router.internal.example', $failedResolver),
    'DNS resolver failure must fail closed'
);

echo "PASS: router target validation checks\n";
