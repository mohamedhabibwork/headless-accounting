<?php

declare(strict_types=1);

namespace Headless\Accounting\HR;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\Branch;
use Headless\Accounting\Tenancy\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'hr_employees';

    protected $fillable = [
        'company_id', 'branch_id', 'department_id', 'manager_id',
        'employee_number', 'first_name', 'last_name',
        'email', 'phone', 'position',
        'hire_date', 'end_date', 'currency',
        'basic_salary_minor', 'hours_per_week', 'paid_leave_days',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'end_date' => 'date',
        'basic_salary_minor' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryComponent::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
