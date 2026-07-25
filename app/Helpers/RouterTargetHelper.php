<?php

namespace App\Helpers;

final class RouterTargetHelper
{
    public static function resolve(string $target, ?callable $resolver = null): string
    {
        $target = trim($target);
        if ($target === '' || strlen($target) > 253 || strpbrk($target, '/\\?#@') !== false) {
            throw new \InvalidArgumentException('Invalid router target');
        }

        if (filter_var($target, FILTER_VALIDATE_IP)) {
            if (! self::isSafeAddress($target)) {
                throw new \InvalidArgumentException('Invalid router target');
            }

            return $target;
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $target)) {
            throw new \InvalidArgumentException('Invalid router target');
        }

        try {
            if ($resolver) {
                $records = $resolver($target);
            } else {
                if (! function_exists('dns_get_record')) {
                    throw new \RuntimeException('DNS resolver unavailable');
                }

                $records = @dns_get_record($target, DNS_A | DNS_AAAA);
            }
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Invalid router target');
        }

        if (! is_array($records) || $records === []) {
            throw new \InvalidArgumentException('Invalid router target');
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = is_string($record)
                ? $record
                : (is_array($record) ? ($record['ip'] ?? $record['ipv6'] ?? null) : null);

            if (! is_string($address) || ! filter_var($address, FILTER_VALIDATE_IP) || ! self::isSafeAddress($address)) {
                throw new \InvalidArgumentException('Invalid router target');
            }

            $addresses[] = $address;
        }

        if ($addresses === []) {
            throw new \InvalidArgumentException('Invalid router target');
        }

        return $addresses[0];
    }

    public static function validate(string $target, ?callable $resolver = null): bool
    {
        try {
            self::resolve($target, $resolver);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private static function isSafeAddress(string $address): bool
    {
        $binary = inet_pton($address);
        if ($binary === false) {
            return false;
        }

        if (strlen($binary) === 4) {
            $bytes = array_values(unpack('C4', $binary));

            return $bytes[0] !== 0
                && $bytes[0] !== 127
                && ! ($bytes[0] === 169 && $bytes[1] === 254)
                && $bytes[0] < 224;
        }

        if ($binary === str_repeat("\0", 16) || $binary === str_repeat("\0", 15)."\1") {
            return false;
        }

        $first = ord($binary[0]);
        $second = ord($binary[1]);
        if ($first === 0xff || ($first === 0xfe && ($second & 0xc0) === 0x80)) {
            return false;
        }

        $embeddedPrefix = substr($binary, 0, 12);
        if ($embeddedPrefix === str_repeat("\0", 12)
            || $embeddedPrefix === str_repeat("\0", 10)."\xff\xff") {
            return self::isSafeAddress(inet_ntop(substr($binary, 12)));
        }

        return true;
    }
}
