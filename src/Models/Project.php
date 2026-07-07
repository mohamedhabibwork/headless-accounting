<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'projects';

    protected $fillable = [
        'company_id', 'code', 'name', 'customer_id',
        'start_date', 'end_date', 'budget_minor',
        'currency', 'progress_pct', 'state',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget_minor' => 'integer',
        'progress_pct' => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function timeBills(): HasMany
    {
        return $this->hasMany(ProjectTimeBill::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }
}
