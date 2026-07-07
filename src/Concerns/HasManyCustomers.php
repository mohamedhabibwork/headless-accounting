<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasManyCustomers — alternative to {@see HasCustomer} for host models
 * that own *more than one* {@see Customer}
 * row (multi-account SaaS, agencies managing clients, …).
 *
 * Hosts typically use this on a `Team`, `Workspace` or `Account` model
 * rather than on `User`.
 *
 * @mixin Model
 */
trait HasManyCustomers
{
    /** @return MorphMany<Customer> */
    public function customers(): MorphMany
    {
        return $this->morphMany(
            Customer::class,
            'owner'
        );
    }

    public function firstCustomer(): ?Customer
    {
        return $this->customers()->orderBy('id')->first();
    }
}
