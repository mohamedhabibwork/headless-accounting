<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasAddresses — drop-in trait so a host-side model can claim
 * ownership of the package's polymorphic `Address` book. Pairs with
 * {@see \Headless\Accounting\Contracts\HasAddresses} — implement that
 * interface on the same model to expose the type-safe accessors
 * the package expects.
 *
 *   $user->addresses;               // MorphMany<Address>
 *   $user->defaultBillingAddress;   // ?Address
 *
 * Schema expected on the host model:
 *
 *     $table->morphs('owner');     // owner_type + owner_id + index
 *
 * @mixin Model
 */
trait HasAddresses
{
    /** @return MorphMany<Address> */
    public function addresses(): MorphMany
    {
        return $this->morphMany(
            Address::class,
            'owner'
        );
    }

    public function defaultBillingAddress(): ?Address
    {
        return $this->addresses()
            ->where('type', 'billing')
            ->where('is_default', true)
            ->first();
    }

    public function defaultShippingAddress(): ?Address
    {
        return $this->addresses()
            ->where('type', 'shipping')
            ->where('is_default', true)
            ->first();
    }

    /** Returns all addresses of a given type (e.g. 'billing', 'shipping'). */
    public function addressesOfType(string $type): MorphMany
    {
        return $this->addresses()->where('type', $type);
    }
}
