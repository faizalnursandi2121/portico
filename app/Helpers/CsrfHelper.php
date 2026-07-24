<?php

namespace App\Helpers;

use App\Core\Session;

class CsrfHelper
{
    const SESSION_KEY = '_csrf_token';

    public static function token()
    {
        Session::start();

        if (! isset($_SESSION[self::SESSION_KEY]) || ! is_string($_SESSION[self::SESSION_KEY])) {
            self::rotate();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function rotate()
    {
        Session::start();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY];
    }

    public static function field()
    {
        return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8').'">';
    }

    public static function validate($token)
    {
        Session::start();
        $storedToken = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($storedToken)
            && is_string($token)
            && hash_equals($storedToken, $token);
    }

    public static function injectFields($html)
    {
        return preg_replace_callback(
            '/<form\b(?=[^>]*\bmethod\s*=\s*(["\'])post\1)[^>]*>/i',
            function ($matches) {
                if (strpos($matches[0], 'name="_csrf"') !== false) {
                    return $matches[0];
                }

                return $matches[0].self::field();
            },
            $html
        );
    }
}
