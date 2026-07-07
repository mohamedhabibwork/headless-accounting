<?php

declare(strict_types=1);

namespace Headless\Accounting\HR;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use BelongsToCompany;

    protected $table = 'hr_payroll_periods';

    protected $fillable = ['company_id', 'name', 'starts_at', 'ends_at', 'pay_date', 'closed'];

    protected $casts = [
        'starts_at' => 'date', 'ends_at' => 'date',
        'pay_date' => 'date', 'closed' => 'boolean',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'period_id');
    }
}
