<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * ImplementsPayable — drop-in trait for any host-side model that
 * already exposes `total_minor`, `paid_minor`, `currency`, and
 * `payments()` to become a first-class {@see Payable}.
 *
 * Schema expected:
 *
 *     $table->bigInteger('total_minor');
 *     $table->bigInteger('paid_minor')->default(0);
 *     $table->string('currency', 3);
 *
 * Usage:
 *
 *     use Illuminate\Database\Eloquent\Model;
 *     use Headless\Accounting\Concerns\ImplementsPayable;
 *
 *     class Booking extends Model implements \Headless\Accounting\Contracts\Payable
 *     {
 *         use ImplementsPayable;
 *     }
 *
 * Hosts that prefer different field names can override the protected
 * `$payableTotalAttribute`, `$payablePaidAttribute` and
 * `$payableCurrencyAttribute` properties (or use {@see HasMoney} for
 * automatic Money helpers around the same fields).
 *
 * @mixin Model
 */
trait ImplementsPayable
{
    public function payments(): MorphMany
    {
        return $this->morphMany(
            Payment::class,
            'payable'
        );
    }

    public function totalDue(): int
    {
        return (int) ($this->attributes[$this->payableTotalAttribute()] ?? 0);
    }

    public function totalPaid(): int
    {
        if (array_key_exists('paid_sum_minor', $this->attributes)) {
            return (int) $this->attributes['paid_sum_minor'];
        }

        if (($this->attributes[$this->payablePaidAttribute()] ?? null) !== null) {
            return (int) $this->attributes[$this->payablePaidAttribute()];
        }

        return (int) $this->payments()
            ->where('state', Payment::STATE_CAPTURED)
            ->sum('amount_minor');
    }

    public function balanceDue(): int
    {
        return max(0, $this->totalDue() - $this->totalPaid());
    }

    public function currency(): string
    {
        return (string) ($this->attributes[$this->payableCurrencyAttribute()] ?? '');
    }

    /**
     * Hook hosts can override to add a deterministic column write
     * (e.g. `paid_minor = sum(payments)`) when the booking is paid.
     */
    public function markPaid(int $amountMinor): void
    {
        $paid = (int) ($this->attributes[$this->payablePaidAttribute()] ?? 0);
        $this->attributes[$this->payablePaidAttribute()] = $paid + $amountMinor;
        $this->save();
    }

    /** Hook to bring total up to date before payment allocation. */
    public function syncTotals(): void {}

    protected function payableTotalAttribute(): string
    {
        return property_exists($this, 'payableTotalAttribute')
            ? $this->payableTotalAttribute
            : 'total_minor';
    }

    protected function payablePaidAttribute(): string
    {
        return property_exists($this, 'payablePaidAttribute')
            ? $this->payablePaidAttribute
            : 'paid_minor';
    }

    protected function payableCurrencyAttribute(): string
    {
        return property_exists($this, 'payableCurrencyAttribute')
            ? $this->payableCurrencyAttribute
            : 'currency';
    }
}
