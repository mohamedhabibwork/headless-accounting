<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'asset_categories';

    protected $fillable = [
        'company_id', 'code', 'name',
        'default_depreciation_method', 'default_useful_life_years',
        'default_residual_pct',
        'asset_account_id', 'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
    ];

    protected $casts = [
        'default_useful_life_years' => 'integer',
        'default_residual_pct' => 'float',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
