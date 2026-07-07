<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\HR\Employee;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use BelongsToCompany;

    protected $table = 'hr_loans';

    protected $fillable = [
        'company_id', 'employee_id', 'vendor_id', 'name', 'kind',
        'currency', 'principal_minor', 'interest_rate_pct',
        'term_months', 'start_date', 'end_date', 'state',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'principal_minor' => 'integer',
        'interest_rate_pct' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }
}
