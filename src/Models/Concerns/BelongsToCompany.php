<?php

declare(strict_types=1);

namespace Headless\Accounting\Models\Concerns;

use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToCompany — every tenant-scoped model carries a `company_id`
 * and inherits:
 *   - a belongsTo company() relation
 *   - a forCompany scope
 *   - automatic current-company filtering applied via CompanyScope (optional)
 *
 * Combine with `CompanyScope` if you want global query-time isolation:
 *
 *     use BelongsToCompany, CompanyScope;
 */
trait BelongsToCompany
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCurrentCompany($query)
    {
        $ctxId = CompanyContext::current()?->id;

        return $ctxId ? $query->where('company_id', $ctxId) : $query;
    }

    /** Resolve the company id at the time of save when global context is set. */
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if ($model->company_id === null) {
                $company = CompanyContext::current();
                if ($company) {
                    $model->company_id = $company->id;
                }
            }
        });
    }

    public function isCompany(int $companyId): bool
    {
        return (int) $this->company_id === $companyId;
    }
}
