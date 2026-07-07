<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Headless\Accounting\Currency\Contracts\ExchangeRateProvider;
use Headless\Accounting\Currency\CurrencyConverter;
use Headless\Accounting\Currency\Money;
use Headless\Accounting\Exceptions\UnknownCurrencyException;
use Illuminate\Cache\ArrayStore;

it('converts between currencies using a static FX provider', function () {
    $provider = new class implements ExchangeRateProvider
    {
        public function rates(string $base, array $quotes, CarbonImmutable $at): array
        {
            return ['EUR' => 1.0, 'USD' => 1.08, 'GBP' => 0.85];
        }
    };

    $converter = new CurrencyConverter(
        $provider,
        new class extends ArrayStore
        {
            public function get($key, $default = null)
            {
                return $default;
            }

            public function put($key, $value, $ttl = null) {}
        },
        ['cache' => ['ttl' => 60], 'default_currency' => 'EUR'],
    );

    $converted = $converter->convert(new Money(10000, 'EUR'), 'USD');
    expect($converted->amount)->toBe(10800);    // 100.00 EUR × 1.08 = 108.00 USD
});

it('returns the money untouched when source and target currencies match', function () {
    $provider = new class implements ExchangeRateProvider
    {
        public function rates(string $b, array $q, CarbonImmutable $a): array
        {
            return [];
        }
    };

    $converter = new CurrencyConverter(
        $provider,
        new class extends ArrayStore
        {
            public function get($key, $default = null)
            {
                return $default;
            }

            public function put($key, $value, $ttl = null) {}
        },
        ['cache' => ['ttl' => 60]],
    );
    $converted = $converter->convert(new Money(999, 'EUR'), 'EUR');
    expect($converted->amount)->toBe(999);
});

it('throws UnknownCurrencyException for an unknown code', function () {
    $provider = new class implements ExchangeRateProvider
    {
        public function rates(string $b, array $q, CarbonImmutable $a): array
        {
            return [];
        }
    };

    $converter = new CurrencyConverter(
        $provider,
        new class extends ArrayStore
        {
            public function get($key, $default = null)
            {
                return $default;
            }

            public function put($key, $value, $ttl = null) {}
        },
        ['cache' => ['ttl' => 60]],
    );

    expect(fn () => $converter->convert(new Money(100, 'EUR'), 'XYZ'))
        ->toThrow(UnknownCurrencyException::class);
});

it('triangulates via the configured default currency when direct rate missing', function () {
    $provider = new class implements ExchangeRateProvider
    {
        public function rates(string $base, array $quotes, CarbonImmutable $at): array
        {
            // EUR-base only.
            return ['EUR' => 1.0, 'USD' => 1.08, 'GBP' => 0.85];
        }
    };

    $cache = new class extends ArrayStore
    {
        public function remember(string $key, $ttl, Closure $cb)
        {
            return $cb();
        }

        public function get($key, $default = null)
        {
            return $default;
        }

        public function put($key, $value, $ttl = null) {}
    };

    $converter = new CurrencyConverter(
        $provider,
        $cache,
        ['cache' => ['ttl' => 60], 'default_currency' => 'EUR'],
    );
    // USD→GBP cross via EUR.
    $rate = $converter->triangulate('USD', 'GBP');
    // 1 EUR = 0.85 GBP, 1 EUR = 1.08 USD → 1 USD = 0.85/1.08 GBP = 0.787…
    expect($rate)->toBeGreaterThan(0.78);
    expect($rate)->toBeLessThan(0.79);
});
