<?php

declare(strict_types=1);

namespace Headless\Accounting\Tenancy;

use Headless\Accounting\Accounting\CostAllocation;
use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'cost_centers';

    protected $fillable = ['company_id', 'branch_id', 'code', 'name', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CostAllocation::class);
    }
}
