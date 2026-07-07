<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringRule extends Model
{
    use BelongsToCompany;

    protected $table = 'aut_recurring_rules';

    protected $fillable = [
        'company_id', 'name', 'kind', 'frequency',
        'day_of_month', 'start_date', 'end_date',
        'next_run_at', 'last_run_at',
        'max_runs', 'runs_count',
        'template', 'active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_at' => 'date',
        'last_run_at' => 'date',
        'template' => 'array',
        'active' => 'boolean',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(RecurringRuleRun::class, 'rule_id');
    }
}
