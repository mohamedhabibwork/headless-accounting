<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringRuleRun extends Model
{
    protected $table = 'aut_recurring_runs';

    protected $fillable = ['rule_id', 'run_at', 'status', 'reference_id', 'error'];

    protected $casts = ['run_at' => 'datetime'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RecurringRule::class, 'rule_id');
    }
}
