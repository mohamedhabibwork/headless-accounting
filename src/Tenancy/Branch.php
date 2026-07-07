<?php

declare(strict_types=1);

namespace Headless\Accounting\Tenancy;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'branches';

    protected $fillable = [
        'company_id', 'code', 'name',
        'address_line1', 'address_line2', 'city', 'region', 'country_code',
        'postal_code', 'phone', 'is_head_office', 'active',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
