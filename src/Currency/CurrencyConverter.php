<?php

declare(strict_types=1);

namespace Headless\Accounting\Currency;

use Carbon\CarbonImmutable;
use Headless\Accounting\Currency\Contracts\ExchangeRateProvider;
use Headless\Accounting\Exceptions\UnknownCurrencyException;
use Headless\Accounting\Support\Config;
use Headless\Accounting\Support\RoundingMode;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * CurrencyConverter — converts Money across currencies using a pluggable
 * ExchangeRateProvider. Results are cached per (base, quote, day) to
 * keep things fast and reproducible.
 */
final class CurrencyConverter
{
    public function __construct(
        private readonly ExchangeRateProvider $provider,
        private readonly CacheRepository $cache,
        private readonly array $config,
    ) {}

    public function convert(Money $money, string $quote, ?CarbonImmutable $at = null): Money
    {
        $at ??= CarbonImmutable::now();

        if ($money->currency === $quote) {
            return $money;
        }

        $baseDecimals = Currency::decimals($money->currency);
        $quoteDecimals = Currency::decimals($quote);

        $rate = $this->rate($money->currency, $quote, $at);

        // Convert minor units of base to minor units of quote via string math.
        $result = bcmul((string) $money->amount, (string) $rate, 8);
        $minor = (int) RoundingMode::roundWith((float) $result * (10 ** $quoteDecimals));

        return new Money($minor, $quote);
    }

    public function rate(string $base, string $quote, ?CarbonImmutable $at = null): float
    {
        $at ??= CarbonImmutable::now();

        if (! Currency::exists($base)) {
            throw new UnknownCurrencyException($base);
        }
        if (! Currency::exists($quote)) {
            throw new UnknownCurrencyException($quote);
        }

        $cacheKey = sprintf('fx:%s:%s:%s', $base, $quote, $at->format('Y-m-d'));
        $ttl = (int) ($this->config['cache']['ttl'] ?? 0);

        return (float) $this->cache->remember($cacheKey, $ttl, function () use ($base, $quote, $at) {
            $rates = $this->provider->rates($base, [$quote], $at);
            if (! isset($rates[$quote])) {
                throw new UnknownCurrencyException("{$base}→{$quote} rate unavailable at {$at}.");
            }

            return $rates[$quote];
        });
    }

    /**
     * Computes a triangulated cross-rate when a direct base→quote rate
     * is not available, using the configured default currency as pivot.
     */
    public function triangulate(string $base, string $quote, ?CarbonImmutable $at = null): float
    {
        $at ??= CarbonImmutable::now();
        $pivot = (string) ($this->config['default_currency']
            ?? Config::get('headless-accounting.currency.default'));

        if ($base === $pivot) {
            return $this->rate($pivot, $quote, $at);
        }
        if ($quote === $pivot) {
            return 1 / $this->rate($pivot, $base, $at);
        }

        $baseToPivot = $this->rate($base, $pivot, $at);
        $pivotToQuote = $this->rate($pivot, $quote, $at);

        return $baseToPivot * $pivotToQuote;
    }
}
