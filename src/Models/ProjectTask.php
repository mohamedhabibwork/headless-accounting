<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends BaseModel
{
    protected string $tableSuffix = 'project_tasks';

    protected $fillable = ['project_id', 'name', 'due_at', 'billable', 'estimated_minutes'];

    protected $casts = ['due_at' => 'date', 'billable' => 'boolean', 'estimated_minutes' => 'integer'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
