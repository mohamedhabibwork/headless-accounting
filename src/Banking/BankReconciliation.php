<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'bank_reconciliations';

    protected $fillable = [
        'company_id', 'bank_account_id',
        'statement_date', 'closing_balance_minor',
        'state', 'metadata',
        'matched_count', 'difference_minor',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'closing_balance_minor' => 'integer',
        'difference_minor' => 'integer',
        'matched_count' => 'integer',
        'metadata' => 'array',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'reconciliation_id');
    }
}
