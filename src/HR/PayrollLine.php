<?php

declare(strict_types=1);

namespace Headless\Accounting\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    protected $table = 'hr_payroll_lines';

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'component_name',
        'type', 'amount_minor', 'currency',
    ];

    protected $casts = ['amount_minor' => 'integer'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
