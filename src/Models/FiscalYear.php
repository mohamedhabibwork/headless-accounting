<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends BaseModel
{
    protected string $tableSuffix = 'fiscal_years';

    protected $fillable = ['name', 'starts_at', 'ends_at', 'closed'];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'closed' => 'boolean',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'fiscal_year_id');
    }
}
