<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransfer extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'bank_transfers';

    protected $fillable = [
        'company_id', 'from_account_id', 'to_account_id',
        'currency', 'amount_minor',
        'fee_minor', 'fx_rate',
        'transfer_date', 'reference',
        'state', 'journal_entry_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'fee_minor' => 'integer',
        'fx_rate' => 'float',
        'transfer_date' => 'date',
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'to_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
