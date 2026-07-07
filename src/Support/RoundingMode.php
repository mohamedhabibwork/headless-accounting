<?php

declare(strict_types=1);

namespace Headless\Accounting\Support;

/**
 * Centralized rounding modes for the package.
 *
 * The canonical names are exposed as constants so call-sites can reference
 * them instead of repeating raw PHP rounding constant strings.
 */
enum RoundingMode: string
{
    case HalfEven = 'half_even';
    case HalfUp = 'half_up';
    case HalfDown = 'half_down';
    case Up = 'up';
    case Down = 'down';

    public static function fromConfig(string $key = 'headless-accounting.currency.rounding'): self
    {
        $value = Config::string($key, self::HalfEven->value);

        return self::tryFrom($value) ?? self::HalfEven;
    }

    public function toPhpConstant(): int
    {
        return match ($this) {
            self::HalfEven => \PHP_ROUND_HALF_EVEN,
            self::HalfUp => \PHP_ROUND_HALF_UP,
            self::HalfDown => \PHP_ROUND_HALF_DOWN,
            self::Up => \PHP_ROUND_HALF_UP,
            self::Down => \PHP_ROUND_HALF_DOWN,
        };
    }

    public function round(float $value, int $precision = 0): float
    {
        return round($value, $precision, $this->toPhpConstant());
    }

    public static function roundWith(float $value, int $precision = 0, ?string $mode = null): float
    {
        $resolved = $mode !== null
            ? (self::tryFrom($mode) ?? self::fromConfig())
            : self::fromConfig();

        return $resolved->round($value, $precision);
    }
}
