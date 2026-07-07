<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends BaseModel
{
    protected string $tableSuffix = 'project_milestones';

    protected $fillable = [
        'project_id', 'name', 'due_at', 'achieved_at',
        'revenue_minor', 'currency', 'invoiced', 'invoice_id',
    ];

    protected $casts = [
        'due_at' => 'date',
        'achieved_at' => 'date',
        'revenue_minor' => 'integer',
        'invoiced' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
