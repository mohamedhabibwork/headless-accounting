<?php

declare(strict_types=1);

namespace Headless\Accounting\Models\Concerns;

use Headless\Accounting\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * CompanyScope — opt-in global scope that auto-filters all queries by
 * the active CompanyContext. Apply to any tenant-scoped Eloquent model:
 *
 *     use BelongsToCompany, CompanyScope;
 *
 * Important: only use this on models that **always** carry a `company_id`.
 */
trait CompanyScope
{
    public static function bootCompanyScope(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $company = CompanyContext::current();
            if ($company) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    $company->id,
                );
            }
        });
    }

    public function scopeWithoutCompany($query)
    {
        return $query->withoutGlobalScope('company');
    }
}
