<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Contracts\Discountable;
use Headless\Accounting\Discounts\DiscountEngine;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ImplementsDiscountable — drop-in trait so any host-side model can
 * participate in the package's polymorphic discount engine.
 *
 * Schema expected:
 *
 *     $table->json('discount_metadata')->nullable();   // optional
 *
 * The trait wires the two relations called out in
 * {@see Discountable}:
 *
 *     $order->discounts;          // MorphMany<Discount>
 *     $order->discountable;       // MorphTo self (NULL — used for reports)
 *
 * Hosts that want to *exclude* themselves from certain discounts can
 * override `acceptsDiscount(Discount $d): bool` on the model.
 *
 * @mixin Model
 */
trait ImplementsDiscountable
{
    /** MorphMany — all discounts bound to this object. */
    public function discounts(): MorphMany
    {
        return $this->morphMany(
            DiscountTarget::class,
            'target'
        )->whereHas('discount');
    }

    /** MorphTo inverse — used by reports and aggregations. */
    public function discountable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Hook used by {@see DiscountEngine}
     * to allow a model to opt out of a discount.
     */
    public function acceptsDiscount(Discount $discount): bool
    {
        return true;
    }
}
