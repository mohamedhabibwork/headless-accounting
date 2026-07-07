<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts;

use Headless\Accounting\Currency\Currency;
use Headless\Accounting\Currency\Money;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Support\Config;
use Headless\Accounting\Support\RoundingMode;

/**
 * BaseDiscountDriver — tiny abstract helper shared by all drivers.
 *
 * Holds the config array, exposes helpers to resolve a default currency,
 * and provides line-subtotal math so each driver doesn't re-implement it.
 */
abstract class BaseDiscountDriver implements DiscountDriver
{
    protected array $config = [];

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    protected function currency(EvaluationContext $ctx): string
    {
        return $this->config('currency')
            ?? (method_exists($ctx->order, 'currency') ? $ctx->order->currency() : null)
            ?? Config::get('headless-accounting.currency.default');
    }

    protected function moneyFromMinor(int $amount, string $currency): Money
    {
        return new Money($amount, $currency);
    }

    protected function lineSubtotal(OrderItem $item): int
    {
        return ((int) $item->unit_price_minor) * ((int) $item->quantity);
    }

    /** Helper to sum an iterable<OrderItem>::lineSubtotal(). */
    protected function subtotal(iterable $items): int
    {
        $s = 0;
        foreach ($items as $i) {
            if ($i instanceof OrderItem) {
                $s += $this->lineSubtotal($i);
            }
        }

        return $s;
    }

    protected function roundingMode(): string
    {
        return Config::string(
            'headless-accounting.currency.rounding',
            RoundingMode::HalfEven->value
        );
    }
}
