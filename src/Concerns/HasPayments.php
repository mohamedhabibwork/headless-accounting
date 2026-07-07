<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasPayments — drop-in trait for any host-side model that should
 * have a polymorphic `payments` relation pointing at the package's
 * {@see Payment} table.
 *
 * Useful when the host has a `Booking` or `Reservation` table that
 * clients of the system should be able to record payments against,
 * without going through Orders / Invoices.
 *
 * @mixin Model
 */
trait HasPayments
{
    /** @return MorphMany<Payment> */
    public function payments(): MorphMany
    {
        return $this->morphMany(
            Payment::class,
            'payable'
        );
    }

    /** Whether this entity has been paid in full. */
    public function isPaid(): bool
    {
        return $this->payments()
            ->where('state', Payment::STATE_CAPTURED)
            ->exists();
    }
}
