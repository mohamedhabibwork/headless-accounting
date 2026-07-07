<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Concerns\HasCustomer;

/**
 * CustomerOwner — host-side contract implemented by User (or any other
 * "person/entity on the platform") to claim ownership of package-side
 * Customer, Vendor, Employee and similar records.
 *
 * Because the package keeps those records polymorphic
 * (`owner_type / owner_id`), every host owns them differently — this
 * interface gives the package a single, type-safe call site:
 *
 *     $user = Auth::user();
 *     $customer = $user->customer;             // property access returns ?Customer
 *     $user->customerRelation()->create([...]); // eager-load / build a new one
 *
 * Implementations should either:
 *   1. Use the {@see HasCustomer} trait
 *      shipped with this package (zero code), or
 *   2. Write their own relationship method that resolves the same
 *      `customer_id` column on the host's users table.
 *
 * The interface declares `customer()` without a return type on purpose:
 * the trait ships an eager-load friendly `MorphOne` while hosts can
 * opt for a strict `?Customer` return type on their own implementation.
 */
interface CustomerOwner
{
    /**
     * The package-side Customer record this host-side model owns.
     *
     * Implementations may also return `null` while the Customer has
     * not yet been provisioned (the package's `LinkCustomer` action
     * provisions it lazily).
     */
    public function customer();
}
