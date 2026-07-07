<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Anything that can receive a payment. The most common implementation is
 * Order and Invoice, but Subscription, ManualReceivable, etc. qualify too.
 */
interface Payable
{
    public function payments(): MorphMany;

    /** Total due in the payable's currency, in *minor units* (cents). */
    public function totalDue(): int;

    /** Total already captured against this payable, in minor units. */
    public function totalPaid(): int;

    /** Outstanding balance in minor units. */
    public function balanceDue(): int;

    /** ISO-4217 currency code, e.g. 'EUR'. */
    public function currency(): string;
}
