<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\HR\Employee;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTimeBill extends BaseModel
{
    protected string $tableSuffix = 'project_time_bills';

    protected $fillable = [
        'project_id', 'task_id', 'employee_id',
        'date', 'minutes', 'hourly_rate_minor',
        'currency', 'amount_minor', 'state', 'invoice_id',
    ];

    protected $casts = [
        'date' => 'date',
        'minutes' => 'integer',
        'amount_minor' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
