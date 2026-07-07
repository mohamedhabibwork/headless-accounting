<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToTenantCompany — alias of {@see BelongsToCompany} that
 * host projects can apply to their own tables whose foreign key is
 * named `tenant_id` (some teams insist on the more descriptive
 * `tenant_id` over `company_id`).
 *
 * The trait works exactly like `BelongsToCompany` but maps to
 * `tenant_id` instead of `company_id`. Hosts that already use the
 * `company_id` name should keep using the existing
 * `BelongsToCompany` trait.
 *
 * @mixin Model
 */
trait BelongsToTenantCompany
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class,
            $this->tenantForeignKey()
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->company();
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where($this->tenantForeignKey(), $companyId);
    }

    public function scopeForCurrentCompany($query)
    {
        $ctxId = CompanyContext::current()?->id;

        return $ctxId ? $query->where($this->tenantForeignKey(), $ctxId) : $query;
    }

    public function isCompany(int $companyId): bool
    {
        return (int) ($this->attributes[$this->tenantForeignKey()] ?? 0) === $companyId;
    }

    /** Boot helper to auto-fill `tenant_id` from the active CompanyContext. */
    protected static function bootBelongsToTenantCompany(): void
    {
        static::creating(function ($model) {
            $key = $model->tenantForeignKey();
            if (($model->attributes[$key] ?? null) === null) {
                $company = CompanyContext::current();
                if ($company) {
                    $model->{$key} = $company->id;
                }
            }
        });
    }

    protected function tenantForeignKey(): string
    {
        return property_exists($this, 'tenantForeignKey')
            ? $this->tenantForeignKey
            : 'tenant_id';
    }
}
