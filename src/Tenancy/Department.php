<?php

declare(strict_types=1);

namespace Headless\Accounting\Tenancy;

use Headless\Accounting\HR\Employee;
use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'departments';

    protected $fillable = ['company_id', 'branch_id', 'manager_id', 'code', 'name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }
}
