<?php

declare(strict_types=1);

namespace Headless\Accounting\Procurement;

use Headless\Accounting\HR\Employee;
use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\Department;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequest extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'purchase_requests';

    protected $fillable = [
        'company_id', 'requested_by', 'department_id', 'number',
        'needed_by', 'state', 'lines', 'justification',
    ];

    protected $casts = ['needed_by' => 'date', 'lines' => 'array'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
