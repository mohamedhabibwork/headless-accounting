<?php

declare(strict_types=1);

namespace Headless\Accounting\Support;

use Headless\Accounting\Currency\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a (amount_minor + currency) pair into a {@see Money} value
 * object — and back. Use like:
 *
 *     protected $casts = [
 *         'amount' => \Headless\Accounting\Support\MoneyCast::class.':gross_amount,currency',
 *     ];
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(public string $amountField, public string $currencyField) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }
        $amount = $attributes[$this->amountField] ?? null;
        $currency = $attributes[$this->currencyField] ?? null;
        if ($amount === null || $currency === null) {
            return null;
        }

        return new Money((int) $amount, (string) $currency);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $value instanceof Money) {
            return [];
        }

        return [
            $this->amountField => $value->amount,
            $this->currencyField => $value->currency,
        ];
    }
}
