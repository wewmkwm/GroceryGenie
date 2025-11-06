<?php
declare(strict_types=1);

/**
 * Environment configuration bootstrap for GroceryGenie.
 * Loads key/value pairs from a project-level .env file (if present) and
 * exposes them via getenv()/$_ENV/$_SERVER. Falls back to system-provided
 * environment variables when the .env file is missing.
 */

if (!function_exists('gg_load_env_file')) {
    /**
     * Parse a simple KEY=VALUE .env file and register each entry in the
     * process environment and superglobals, without overriding existing values.
     */
    function gg_load_env_file(string $filePath): void
    {
        if (!is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            if (stripos($name, 'export ') === 0) {
                $name = trim(substr($name, 7));
            }

            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $value = str_replace(['\\n', '\\r'], ["\n", "\r"], $value);

            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }

            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = $value;
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('gg_env')) {
    /**
     * Retrieve an environment variable with an optional default.
     */
    function gg_env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

$projectRoot = dirname(__DIR__);
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
gg_load_env_file($envPath);

if (!defined('GG_HUGGINGFACE_TOKEN')) {
    $hfToken = gg_env('HUGGINGFACE_TOKEN');
    define('GG_HUGGINGFACE_TOKEN', $hfToken !== null ? $hfToken : null);
}
