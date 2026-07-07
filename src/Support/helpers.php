<?php

declare(strict_types=1);

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Pricing\PricingResolver;
use Headless\Accounting\Pricing\ResolvedPrice;
use Headless\Accounting\Support\Config;

if (! function_exists('money')) {
    /**
     * Convenience helper for tests / Tinker:
     *
     *     money(999, 'EUR');       // → Money
     *     money('9.99', 'EUR');    // → Money (from decimal)
     *     money(999);              // → Money::zero(config default)
     */
    function money(int|string $amount, ?string $currency = null): Money
    {
        if (is_string($amount)) {
            $currency ??= Config::string('headless-accounting.currency.default');

            return Money::fromFloat((float) $amount, $currency);
        }

        $currency ??= Config::string('headless-accounting.currency.default');

        return new Money($amount, $currency);
    }
}

if (! function_exists('pricing')) {
    /**
     * Resolve the price for a variant in a single line of code:
     *
     *     pricing($variant, currency: 'EUR', customer: $customer)
     *         ->amount
     *         ->format();
     */
    function pricing(ProductVariant $variant, ?string $currency = null, mixed $channel = null, mixed $customer = null, int $quantity = 1, ?string $locale = null): ResolvedPrice
    {
        $currency ??= Config::string('headless-accounting.currency.default');
        $locale ??= Config::string('headless-accounting.locale.default');

        return app(PricingResolver::class)
            ->resolve($variant, $currency, $channel, $customer, $quantity, locale: $locale);
    }
}
