<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Currency\Currency;
use Headless\Accounting\Currency\Money;

/**
 * HasMoney — drop-in trait for Eloquent models that hold a single amount
 * in minor units and an ISO-4217 currency code.
 *
 * Example schema:
 *
 *     $table->bigInteger('amount_minor');
 *     $table->string('currency', 3);
 */
trait HasMoney
{
    public function money(): Money
    {
        return new Money(
            (int) $this->{$this->moneyAmountAttribute()},
            $this->{$this->moneyCurrencyAttribute()},
        );
    }

    public function setMoney(Money $money): void
    {
        $this->{$this->moneyAmountAttribute()} = $money->amount;
        $this->{$this->moneyCurrencyAttribute()} = $money->currency;
    }

    public function moneyAmountAttribute(): string
    {
        return property_exists($this, 'moneyAmountAttribute')
            ? $this->moneyAmountAttribute
            : 'amount_minor';
    }

    public function moneyCurrencyAttribute(): string
    {
        return property_exists($this, 'moneyCurrencyAttribute')
            ? $this->moneyCurrencyAttribute
            : 'currency';
    }

    public function getMoneyDecimals(): int
    {
        return Currency::decimals($this->{$this->moneyCurrencyAttribute()});
    }
}
