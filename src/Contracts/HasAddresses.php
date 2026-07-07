<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Models\Address;

/**
 * HasAddresses — host-side contract for any model that owns an Address
 * book (Customer, Vendor, User, …). The package uses a polymorphic
 * `Address` row pointed at `owner_type / owner_id`, but a host project
 * often prefers to expose type-safe helpers ("myBillingAddress()",
 * "myShippingAddresses()") on its own User model.
 *
 * Implementations are free to return the package's {@see Address} row
 * directly, or a host-side Address model that wraps it — the package
 * only consumes {@see Address::formatted()}.
 */
interface HasAddresses
{
    /** Returns all addresses of the given type (e.g. 'billing', 'shipping'). */
    public function addressesOfType(string $type): iterable;

    /** Returns the owner's preferred default address for a given type. */
    public function defaultAddress(string $type): ?Address;
}
