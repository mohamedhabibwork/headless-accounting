<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Contracts\CustomerOwner;
use Headless\Accounting\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * HasCustomer — drop-in trait for a host-side `User` (or any other
 * "actor") model so it can claim ownership of a package-side
 * {@see Customer} row.
 *
 * The trait exposes a polymorphic relationship pointed at the same
 * `owner_type / owner_id` columns the package already uses for the
 * `Customer`, `Vendor` and `Employee` tables. It also implements
 * {@see CustomerOwner} for free — the relation is exposed via
 * `customerRelation()`, and the simple `customer()` getter returns
 * the actual model (or null).
 *
 * Required host-side columns:
 *
 *     $table->morphs('owner');             // adds owner_type + owner_id + index
 *     – or –
 *     $table->string('owner_type')->nullable();
 *     $table->unsignedBigInteger('owner_id')->nullable();
 *     $table->index(['owner_type', 'owner_id']);
 *
 * Usage:
 *
 *     use Headless\Accounting\Concerns\HasCustomer;
 *
 *     class User extends Authenticatable implements CustomerOwner
 *     {
 *         use HasCustomer;
 *     }
 *
 *     $user->customer;                // ?Customer
 *     $user->customerRelation()->create(['email' => $user->email]);
 *     $user->getOrCreateCustomer();   // Customer (lazily provisioned)
 *
 * @mixin Model
 */
trait HasCustomer
{
    /**
     * FQCN of the related model. Override to bind to a custom User
     * implementation that still extends {@see Customer}.
     *
     * @var class-string<Customer>
     */
    protected string $customerModel = Customer::class;

    /**
     * Returns the underlying polymorphic 1:1 relation. Hosts that need
     * the relation (e.g. for eager-loading) call this method directly.
     */
    public function customerRelation(): MorphOne
    {
        return $this->morphOne($this->customerModel, 'owner');
    }

    /**
     * Eager-load the relation by aliasing the method to "customer".
     * Hosts that want to query `$user->load('customer')` can rely on
     * this `customer()` morphOne which still satisfies Eloquent's
     * relation loading semantics.
     *
     * This intentionally returns a MorphOne — the {@see CustomerOwner}
     * contract is satisfied through the magic `customer()` getter
     * below (via `__get`). Returning a MorphOne here keeps
     * `withCount('customer')` and other Eloquent helpers working.
     */
    public function customer(): MorphOne
    {
        return $this->customerRelation();
    }

    /**
     * Magic getter — exposes the resolved Customer (or null) when
     * accessed as a property: `$user->customer`.
     *
     * Implemented via {@see HasAttributes::__get}
     * which first looks for accessors, then for `customer` column,
     * and ultimately falls back to attributes. Because we also
     * expose `customer()` returning a MorphOne, the standard
     * `with('customer')` / `load('customer')` patterns keep working.
     */
    public function getCustomerAttribute(): ?Customer
    {
        return $this->customerRelation()->first();
    }

    /**
     * Convenience: returns the {@see Customer}
     * row for this user, lazily creating one with sensible defaults
     * the first time it is asked for.
     */
    public function getOrCreateCustomer(): Customer
    {
        return $this->customerRelation()->firstOrCreate(
            [],
            [
                'email' => (string) ($this->attributes[$this->emailAttribute()] ?? ''),
                'first_name' => (string) ($this->attributes[$this->firstNameAttribute()] ?? ''),
                'last_name' => (string) ($this->attributes[$this->lastNameAttribute()] ?? ''),
                'default_currency' => (string) ($this->attributes[$this->defaultCurrencyAttribute()] ?? 'EUR'),
                'default_locale' => (string) ($this->attributes[$this->defaultLocaleAttribute()] ?? config('app.locale', 'en')),
            ]
        );
    }

    protected function emailAttribute(): string
    {
        return property_exists($this, 'emailAttribute')
            ? $this->emailAttribute
            : 'email';
    }

    protected function firstNameAttribute(): string
    {
        return property_exists($this, 'firstNameAttribute')
            ? $this->firstNameAttribute
            : 'first_name';
    }

    protected function lastNameAttribute(): string
    {
        return property_exists($this, 'lastNameAttribute')
            ? $this->lastNameAttribute
            : 'last_name';
    }

    protected function defaultCurrencyAttribute(): string
    {
        return property_exists($this, 'defaultCurrencyAttribute')
            ? $this->defaultCurrencyAttribute
            : 'default_currency';
    }

    protected function defaultLocaleAttribute(): string
    {
        return property_exists($this, 'defaultLocaleAttribute')
            ? $this->defaultLocaleAttribute
            : 'default_locale';
    }
}
