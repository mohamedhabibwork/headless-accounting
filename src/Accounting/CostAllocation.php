<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\CostCenter;
use Headless\Accounting\Tenancy\ProfitCenter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostAllocation extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'cost_allocations';

    protected $fillable = [
        'company_id', 'source_type', 'source_id',
        'cost_center_id', 'profit_center_id',
        'currency', 'amount_minor', 'percentage', 'allocated_at',
    ];

    protected $casts = [
        'allocated_at' => 'datetime',
        'amount_minor' => 'integer',
        'percentage' => 'float',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class);
    }
}
