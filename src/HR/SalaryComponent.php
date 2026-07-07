<?php

declare(strict_types=1);

namespace Headless\Accounting\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryComponent extends Model
{
    protected $table = 'hr_salary_components';

    protected $fillable = ['employee_id', 'name', 'type', 'calc', 'amount', 'currency'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
