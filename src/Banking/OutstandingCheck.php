<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutstandingCheck extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'outstanding_checks';

    protected $fillable = [
        'company_id', 'bank_account_id', 'check_number',
        'issued_at', 'amount_minor', 'currency',
        'payee', 'cleared_at', 'voided_at', 'state',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'cleared_at' => 'date',
        'voided_at' => 'date',
        'amount_minor' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
