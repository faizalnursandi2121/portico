<?php

namespace App\Config;

class SiteConfig
{
    const APP_NAME = 'MIVO';

    const APP_VERSION = 'v1.2.3';

    const APP_FULL_NAME = 'MIVO - Mikrotik Voucher';

    const CREDIT_NAME = 'MivoDev';

    const CREDIT_URL = 'https://github.com/mivodev';

    const YEAR = '2026';

    const REPO_URL = 'https://github.com/mivodev/mivo';

    public static function getEnvironment()
    {
        $environment = getenv('APP_ENV');

        if ($environment === false || trim($environment) === '') {
            return 'production';
        }

        $environment = strtolower(trim($environment));

        return in_array($environment, ['production', 'staging', 'development', 'testing'], true)
            ? $environment
            : 'production';
    }

    public static function isDebugEnabled()
    {
        if (self::getEnvironment() === 'production') {
            return false;
        }

        $debug = getenv('APP_DEBUG');
        if ($debug === false || trim($debug) === '') {
            return false;
        }

        return filter_var($debug, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    public static function hasSecretKey()
    {
        $key = getenv('APP_KEY');

        return $key !== false && strlen(trim($key)) >= 32;
    }

    public static function getSecretKey()
    {
        if (! self::hasSecretKey()) {
            throw new \RuntimeException('APP_KEY is required and must contain at least 32 characters.');
        }

        return trim((string) getenv('APP_KEY'));
    }

    /**
     * Get the formatted page title
     */
    public static function getTitle($page = '')
    {
        return empty($page) ? self::APP_NAME : $page.' | '.self::APP_NAME;
    }

    /**
     * Get footer text
     */
    public static function getFooter()
    {
        $currentYear = date('Y');
        $yearDisplay = ($currentYear == self::YEAR) ? self::YEAR : self::YEAR.' - '.$currentYear;

        return self::APP_FULL_NAME.' &copy; 2026 - '.$yearDisplay.' &bull; Created with Love <i data-lucide="heart" class="w-3 h-3 inline text-red-500 fill-red-500 mx-1"></i> Developed by <a href="'.self::CREDIT_URL.'" target="_blank" class="font-medium hover:text-foreground transition-colors">'.self::CREDIT_NAME.'</a>';
    }
}
