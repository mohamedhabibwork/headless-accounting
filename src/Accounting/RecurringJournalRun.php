<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringJournalRun extends BaseModel
{
    protected string $tableSuffix = 'recurring_journal_runs';

    protected $fillable = [
        'recurring_journal_id', 'run_at', 'status',
        'journal_entry_id', 'error',
    ];

    protected $casts = ['run_at' => 'datetime'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RecurringJournal::class, 'recurring_journal_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
