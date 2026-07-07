<?php

declare(strict_types=1);

namespace Headless\Accounting\Support;

/**
 * Config — defensive wrapper around Laravel's `config()` helper.
 *
 * The package can be used outside a fully-bootstrapped Laravel application
 * (e.g. unit tests that instantiate models without TestCase). Direct calls
 * to the global `config()` helper throw when the container is unconfigured.
 * Use {@see Config::get()} everywhere so the package degrades gracefully.
 */
final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (! function_exists('app')) {
            return $default;
        }

        try {
            $app = app();
            if (! method_exists($app, 'bound') || ! $app->bound('config')) {
                return $default;
            }

            return $app->make('config')->get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function string(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default);
    }

    public static function array(string $key, array $default = []): array
    {
        return (array) self::get($key, $default);
    }

    public static function float(string $key, float $default = 0.0): float
    {
        return (float) self::get($key, $default);
    }
}
