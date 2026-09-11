<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 Compliant Autoloader for Secure360 Core PHP Architecture
 */
class Autoloader
{
    /**
     * Namespace prefix to map
     * @var string
     */
    protected static string $prefix = 'App\\';

    /**
     * Base directory corresponding to the prefix
     * @var string
     */
    protected static string $baseDir = '';

    /**
     * Register the autoloader with spl_autoload_register
     *
     * @param string $baseDir
     * @return void
     */
    public static function register(string $baseDir): void
    {
        self::$baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        spl_autoload_register(function (string $class) {
            $prefixLength = strlen(self::$prefix);

            if (strncmp(self::$prefix, $class, $prefixLength) !== 0) {
                return;
            }

            $relativeClass = substr($class, $prefixLength);
            $file = self::$baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
