<?php

declare(strict_types=1);

namespace Headless\Accounting\Currency;

/**
 * Currency — small registry of ISO-4217 data (symbol, decimals, formatting
 * conventions). Loaded once and immutable.
 */
final class Currency
{
    /**
     * @var array<string, array{name: string, symbol: string, decimals: int}>
     */
    private static array $registry = [
        'EUR' => ['name' => 'Euro',                 'symbol' => '€', 'decimals' => 2],
        'USD' => ['name' => 'US Dollar',            'symbol' => '$', 'decimals' => 2],
        'GBP' => ['name' => 'Pound Sterling',       'symbol' => '£', 'decimals' => 2],
        'JPY' => ['name' => 'Japanese Yen',         'symbol' => '¥', 'decimals' => 0],
        'CHF' => ['name' => 'Swiss Franc',          'symbol' => 'CHF', 'decimals' => 2],
        'CAD' => ['name' => 'Canadian Dollar',      'symbol' => 'CA$', 'decimals' => 2],
        'AUD' => ['name' => 'Australian Dollar',    'symbol' => 'A$', 'decimals' => 2],
        'SEK' => ['name' => 'Swedish Krona',        'symbol' => 'kr', 'decimals' => 2],
        'NOK' => ['name' => 'Norwegian Krone',      'symbol' => 'kr', 'decimals' => 2],
        'DKK' => ['name' => 'Danish Krone',         'symbol' => 'kr', 'decimals' => 2],
        'PLN' => ['name' => 'Polish Zloty',         'symbol' => 'zł', 'decimals' => 2],
        'CNY' => ['name' => 'Chinese Yuan',         'symbol' => '¥', 'decimals' => 2],
        'INR' => ['name' => 'Indian Rupee',         'symbol' => '₹', 'decimals' => 2],
        'BRL' => ['name' => 'Brazilian Real',       'symbol' => 'R$', 'decimals' => 2],
    ];

    public static function exists(string $code): bool
    {
        return isset(self::$registry[strtoupper($code)]);
    }

    /** @return array{name:string,symbol:string,decimals:int}|null */
    public static function get(string $code): ?array
    {
        $code = strtoupper($code);

        return self::$registry[$code] ?? null;
    }

    public static function decimals(string $code): int
    {
        return self::get($code)['decimals'] ?? 2;
    }

    public static function symbol(string $code): string
    {
        return self::get($code)['symbol'] ?? $code;
    }

    /**
     * Locale-aware decimal separator. Falls back to '.' for unknown locales.
     */
    public static function decimalSeparator(string $locale): string
    {
        return match (substr($locale, 0, 2)) {
            'fr', 'de', 'es', 'it', 'nl', 'pl', 'pt', 'ru', 'sv', 'no', 'da' => ',',
            default => '.',
        };
    }

    /**
     * Locale-aware thousands separator. Uses narrow no-break space (U+202F)
     * by default for typographic correctness in French typography.
     */
    public static function thousandsSeparator(string $locale): string
    {
        return match (substr($locale, 0, 2)) {
            'en' => ',',
            'fr', 'de', 'es', 'it', 'nl', 'pl', 'pt', 'ru', 'sv', 'no', 'da' => "\u{202F}",
            'ja' => ',',
            default => ',',
        };
    }

    /** Add or override a currency (used by package consumers / tests). */
    public static function register(string $code, string $name, string $symbol, int $decimals = 2): void
    {
        self::$registry[strtoupper($code)] = compact('name', 'symbol', 'decimals');
    }

    /** @return string[] List of registered ISO-4217 codes. */
    public static function codes(): array
    {
        return array_keys(self::$registry);
    }
}
