<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Exceptions\UnbalancedJournalException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends BaseModel
{
    protected string $tableSuffix = 'journal_entries';

    protected $fillable = [
        'number', 'source_type', 'source_id',
        'period_id', 'currency',
        'posted_at', 'description',
        'auto_posted',
    ];

    protected $casts = [
        'posted_at' => 'date',
        'auto_posted' => 'boolean',
    ];

    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function balance(): array
    {
        $debit = (int) $this->postings()->sum('debit_minor');
        $credit = (int) $this->postings()->sum('credit_minor');

        return ['debit' => $debit, 'credit' => $credit, 'balanced' => $debit === $credit];
    }

    /** @throws UnbalancedJournalException */
    public function assertBalanced(): void
    {
        $debit = (int) $this->postings()->sum('debit_minor');
        $credit = (int) $this->postings()->sum('credit_minor');

        if ($debit !== $credit) {
            throw new UnbalancedJournalException(
                "Journal entry {$this->number} is unbalanced: debit={$debit} credit={$credit}."
            );
        }
    }
}
