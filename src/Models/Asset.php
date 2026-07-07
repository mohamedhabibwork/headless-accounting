<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends BaseModel
{
    use BelongsToCompany, SoftDeletes;

    protected string $tableSuffix = 'assets';

    protected $fillable = [
        'company_id', 'category_id', 'code', 'name', 'description', 'serial_number',
        'purchase_date', 'in_service_date', 'disposed_at',
        'currency', 'cost_minor', 'residual_minor', 'accumulated_depreciation_minor',
        'depreciation_method', 'useful_life_years', 'depreciation_rate_pct',
        'location_id', 'custodian_id', 'state', 'chart_account_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'in_service_date' => 'date',
        'disposed_at' => 'date',
        'cost_minor' => 'integer',
        'residual_minor' => 'integer',
        'accumulated_depreciation_minor' => 'integer',
        'useful_life_years' => 'integer',
        'depreciation_rate_pct' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function depreciationLines(): HasMany
    {
        return $this->hasMany(DepreciationLine::class);
    }

    public function bookValueMinor(): int
    {
        return (int) $this->cost_minor - (int) $this->accumulated_depreciation_minor;
    }
}
