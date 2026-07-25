<?php

namespace App\Helpers;

use App\Config\SiteConfig;

class EncryptionHelper
{
    public static function encrypt($text)
    {
        if (empty($text)) {
            return '';
        }

        $key = SiteConfig::getSecretKey();
        $ivLength = openssl_cipher_iv_length('aes-256-gcm');
        if (! is_int($ivLength) || $ivLength <= 0) {
            throw new \RuntimeException('Unable to determine AES-256-GCM IV length.');
        }

        $iv = random_bytes($ivLength);
        $tag = null;
        $encrypted = openssl_encrypt($text, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

        if ($encrypted === false || ! is_string($tag) || strlen($tag) !== 16) {
            throw new \RuntimeException('AES-256-GCM encryption failed.');
        }

        return 'v2:'.base64_encode($iv.$tag.$encrypted);
    }

    public static function decrypt($text)
    {
        if (empty($text)) {
            return '';
        }

        $key = SiteConfig::getSecretKey();

        if (str_starts_with($text, 'v2:')) {
            $decoded = base64_decode(substr($text, 3), true);
            $ivLength = openssl_cipher_iv_length('aes-256-gcm');
            if ($decoded === false || ! is_int($ivLength) || $ivLength <= 0 || strlen($decoded) <= $ivLength + 16) {
                return false;
            }

            $iv = substr($decoded, 0, $ivLength);
            $tag = substr($decoded, $ivLength, 16);
            $encrypted = substr($decoded, $ivLength + 16);

            if (strlen($iv) !== $ivLength || strlen($tag) !== 16 || $encrypted === '') {
                return false;
            }

            return openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        }

        $decoded = base64_decode($text, true);
        if ($decoded === false) {
            return $text;
        }

        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) {
            return $text;
        }

        [$encryptedData, $iv] = $parts;

        return openssl_decrypt($encryptedData, 'aes-256-cbc', $key, 0, $iv);
    }

    public static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
