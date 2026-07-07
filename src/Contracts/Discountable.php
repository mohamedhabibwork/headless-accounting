<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Anything a discount can be applied to.
 *
 * The "apply side" of a polymorphic discount — the candidate that is being
 * discounted (order, line item, customer, channel).
 */
interface Discountable
{
    /** Morph relation of all discounts bound to this object. */
    public function discounts(): MorphMany;

    /** Inverse relation for "owner" when needed by reports. */
    public function discountable(): MorphTo;
}
