<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasVendor — drop-in trait for a host-side model to claim ownership
 * of package-side {@see Vendor} rows
 * (procurement side). Pairs with {@see HasCustomer} on the same model.
 *
 * @mixin Model
 */
trait HasVendor
{
    /** @return MorphMany<Vendor> */
    public function vendors(): MorphMany
    {
        return $this->morphMany(
            Vendor::class,
            'owner'
        );
    }

    public function firstVendor(): ?Vendor
    {
        return $this->vendors()->orderBy('id')->first();
    }
}
