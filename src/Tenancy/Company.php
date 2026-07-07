<?php

declare(strict_types=1);

namespace Headless\Accounting\Tenancy;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'companies';

    protected $fillable = [
        'code', 'name', 'legal_name', 'tax_id', 'registration_number',
        'base_currency', 'reporting_currency', 'locale', 'timezone',
        'fiscal_year_start', 'logo_url', 'branding',
        'accounting_policies', 'active',
    ];

    protected $casts = [
        'branding' => 'array',
        'accounting_policies' => 'array',
        'active' => 'boolean',
        'fiscal_year_start' => 'date',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function defaultBranch(): ?Branch
    {
        return $this->branches()->where('is_head_office', true)->first()
            ?? $this->branches()->first();
    }
}
