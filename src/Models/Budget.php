<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\CostCenter;
use Headless\Accounting\Tenancy\Department;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'budgets';

    protected $fillable = [
        'company_id', 'name', 'scope',
        'department_id', 'cost_center_id', 'project_id',
        'year', 'currency', 'approved',
    ];

    protected $casts = ['year' => 'integer', 'approved' => 'boolean'];

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
