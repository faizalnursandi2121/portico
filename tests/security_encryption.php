<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Helpers\EncryptionHelper;

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

function assertThrows(callable $callback, string $expectedMessage, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        assertSameValue($expectedMessage, $exception->getMessage(), $message);

        return;
    }

    throw new RuntimeException('FAIL: '.$message.' (no exception thrown)');
}

function encryptLegacyCbc(string $plaintext, string $key): string
{
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    assertTrue(is_int($ivLength) && $ivLength > 0, 'legacy CBC IV length must be available');

    $iv = random_bytes($ivLength);
    $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, 0, $iv);
    assertTrue($encrypted !== false, 'legacy CBC fixture must encrypt');

    return base64_encode($encrypted.'::'.$iv);
}

function tamperCiphertext(string $ciphertext): string
{
    $encoded = substr($ciphertext, 3);
    $decoded = base64_decode($encoded, true);
    assertTrue($decoded !== false, 'v2 payload must be valid base64 for tamper test');
    assertTrue(strlen($decoded) > 28, 'v2 payload must contain iv, tag, and ciphertext');

    $offset = strlen($decoded) - 1;
    $decoded[$offset] = chr(ord($decoded[$offset]) ^ 0x01);

    return 'v2:'.base64_encode($decoded);
}

$key = str_repeat('k', 32);
$plaintext = 'Sup3r S3cret value!';
putenv('APP_KEY='.$key);

$emptyCiphertext = EncryptionHelper::encrypt('');
assertSameValue('', $emptyCiphertext, 'encrypt must preserve empty strings');
assertSameValue('', EncryptionHelper::decrypt(''), 'decrypt must preserve empty strings');

$ciphertext = EncryptionHelper::encrypt($plaintext);
assertTrue(str_starts_with($ciphertext, 'v2:'), 'new ciphertext must use v2 prefix');
assertSameValue($plaintext, EncryptionHelper::decrypt($ciphertext), 'new ciphertext must decrypt to the original value');
assertSameValue($plaintext, EncryptionHelper::decrypt($plaintext), 'unprefixed plaintext must pass through unchanged');
assertSameValue(false, EncryptionHelper::decrypt('v2:not-base64'), 'invalid base64 v2 payload must fail closed');
assertSameValue(false, EncryptionHelper::decrypt('v2:'.base64_encode('short')), 'short decoded v2 payload must fail closed');

$tamperedCiphertext = tamperCiphertext($ciphertext);
assertSameValue(false, EncryptionHelper::decrypt($tamperedCiphertext), 'tampered v2 ciphertext must fail closed');

$legacyCiphertext = encryptLegacyCbc($plaintext, $key);
assertSameValue($plaintext, EncryptionHelper::decrypt($legacyCiphertext), 'legacy CBC ciphertext must still decrypt');

putenv('APP_KEY=');
assertThrows(
    static fn () => EncryptionHelper::encrypt($plaintext),
    'APP_KEY is required and must contain at least 32 characters.',
    'missing APP_KEY must be rejected for encrypt'
);

putenv('APP_KEY='.str_repeat('s', 31));
assertThrows(
    static fn () => EncryptionHelper::decrypt('plain-text-value'),
    'APP_KEY is required and must contain at least 32 characters.',
    'short APP_KEY must be rejected for decrypt without plaintext fallback'
);

echo "PASS: Encryption helper security checks\n";
