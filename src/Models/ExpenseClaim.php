<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\HR\Employee;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\Department;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseClaim extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'expense_claims';

    protected $fillable = [
        'company_id', 'employee_id', 'department_id', 'project_id',
        'number', 'expense_date', 'state', 'currency', 'total_minor',
        'approval_id', 'description',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'total_minor' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class, 'claim_id');
    }
}
