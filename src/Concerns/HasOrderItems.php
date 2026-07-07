<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Contracts\OrderSubject;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * HasOrderItems — drop-in trait for host-side models that conceptually
 * "contain" the same payload as an Order line: variant_id, quantity,
 * unit_price_minor and currency. The package's pricing engine expects
 * to iterate over a collection of OrderItems, but a host often has the
 * same shape on its own Bookings or Reservations table.
 *
 * Pair with the {@see OrderSubject}
 * interface on the same model to enable `CreateOrderFromSubject`.
 *
 * @mixin Model
 */
trait HasOrderItems
{
    /**
     * Returns the items as an iterable of arrays shaped like:
     *     [
     *         'variant_id' => …,
     *         'quantity' => …,
     *         'unit_price_minor' => …,
     *         'currency' => …,
     *         'name' => …,
     *         'sku' => …,
     *     ]
     *
     * Override on the model if your storage shape differs.
     *
     * @return iterable<array<string,mixed>>
     */
    public function candidateLines(): iterable
    {
        return [];
    }

    /** Optional: shipping in minor units (override per-model). */
    public function shippingMinor(): int
    {
        return 0;
    }

    /** Optional: discount total in minor units (override per-model). */
    public function discountTotalMinor(): int
    {
        return 0;
    }

    /** Channel code, defaulting to package default channel. */
    public function channel(): string
    {
        $value = $this->readRawAttribute($this->channelColumn());

        return (string) ($value ?: Config::string(
            'headless-accounting.channels.default'
        ));
    }

    public function currency(): string
    {
        $value = $this->readRawAttribute($this->currencyColumn());

        return (string) ($value ?: Config::string(
            'headless-accounting.currency.default'
        ));
    }

    public function locale(): ?string
    {
        $value = $this->readRawAttribute($this->localeColumn());

        return $value !== null ? (string) $value : null;
    }

    /**
     * Read an attribute value straight from the in-memory array. We
     * deliberately do NOT use `$this->{$column}` here because Eloquent
     * will try to interpret the method as a relationship if a method
     * with the same name as the column exists on the model (e.g.
     * `currency()`, `channel()`).
     */
    protected function readRawAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    protected function channelColumn(): string
    {
        return property_exists($this, 'channelColumn')
            ? $this->channelColumn
            : 'channel_code';
    }

    protected function currencyColumn(): string
    {
        return property_exists($this, 'currencyColumn')
            ? $this->currencyColumn
            : 'currency';
    }

    protected function localeColumn(): string
    {
        return property_exists($this, 'localeColumn')
            ? $this->localeColumn
            : 'locale';
    }
}
