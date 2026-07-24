<?php

namespace App\Core;

class Session
{
    public static function configure()
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        if (ini_set('session.use_strict_mode', '1') === false
            || ini_set('session.use_only_cookies', '1') === false
            || ini_get('session.use_strict_mode') !== '1'
            || ini_get('session.use_only_cookies') !== '1') {
            throw new \RuntimeException('Secure session settings could not be applied.');
        }

        $current = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $current['lifetime'],
            'path' => $current['path'] ?: '/',
            'domain' => $current['domain'],
            'secure' => self::isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::configure();
            if (! session_start()) {
                throw new \RuntimeException('Session could not be started securely.');
            }
        }
    }

    public static function regenerateId()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (! session_regenerate_id(true)) {
                throw new \RuntimeException('Session ID could not be regenerated securely.');
            }
        }
    }

    public static function destroy()
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            if (! setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ])) {
                throw new \RuntimeException('Session cookie could not be cleared securely.');
            }
        }

        if (! session_destroy()) {
            throw new \RuntimeException('Session data could not be destroyed securely.');
        }
    }

    private static function isHttpsRequest()
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        if (strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') {
            return true;
        }

        $forwardedProtocol = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 2)[0]));

        return $forwardedProtocol === 'https';
    }
}
