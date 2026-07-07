<?php

declare(strict_types=1);

namespace Headless\Accounting\Support;

/**
 * Numbers — small arithmetic helpers shared across drivers and
 * order math. All functions are explicit about rounding mode to keep
 * the package internally consistent with the configured rounding.
 */
final class Numbers
{
    public static function roundHalfEven(int $value, int $decimals = 0): int
    {
        return (int) round((float) $value, $decimals, PHP_ROUND_HALF_EVEN);
    }

    public static function roundHalfUp(int $value, int $decimals = 0): int
    {
        return (int) round((float) $value, $decimals, PHP_ROUND_HALF_UP);
    }

    public static function percent(int $value, float $percent, string $mode = 'half_even'): int
    {
        return match ($mode) {
            'half_even' => (int) round(bcmul((string) $value, (string) ($percent / 100), 8), 0, PHP_ROUND_HALF_EVEN),
            'half_up' => (int) round(bcmul((string) $value, (string) ($percent / 100), 8), 0, PHP_ROUND_HALF_UP),
            'down' => (int) floor(bcmul((string) $value, (string) ($percent / 100), 8)),
            'up' => (int) ceil(bcmul((string) $value, (string) ($percent / 100), 8)),
            default => throw new \InvalidArgumentException("Unknown rounding: {$mode}"),
        };
    }
}
