<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Posting extends BaseModel
{
    protected string $tableSuffix = 'postings';

    protected $fillable = [
        'journal_entry_id', 'account_id',
        'debit_minor', 'credit_minor',
        'currency', 'memo',
    ];

    protected $casts = [
        'debit_minor' => 'integer',
        'credit_minor' => 'integer',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
