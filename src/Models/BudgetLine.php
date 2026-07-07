<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends BaseModel
{
    protected string $tableSuffix = 'budget_lines';

    protected $fillable = [
        'budget_id', 'account_id', 'currency',
        'month', 'planned_minor', 'revised_minor',
        'actual_minor', 'variance_pct',
    ];

    protected $casts = [
        'month' => 'integer', 'planned_minor' => 'integer',
        'revised_minor' => 'integer', 'actual_minor' => 'integer',
        'variance_pct' => 'float',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
