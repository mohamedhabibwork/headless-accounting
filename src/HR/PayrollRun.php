<?php

declare(strict_types=1);

namespace Headless\Accounting\HR;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToCompany;

    protected $table = 'hr_payroll_runs';

    protected $fillable = [
        'company_id', 'period_id', 'run_at', 'state',
        'gross_minor', 'net_minor', 'taxes_minor',
        'social_insurance_minor', 'currency', 'journal_entry_id',
    ];

    protected $casts = [
        'run_at' => 'datetime',
        'gross_minor' => 'integer',
        'net_minor' => 'integer',
        'taxes_minor' => 'integer',
        'social_insurance_minor' => 'integer',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
