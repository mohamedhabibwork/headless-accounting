<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPeriod extends BaseModel
{
    protected string $tableSuffix = 'accounting_periods';

    protected $fillable = ['fiscal_year_id', 'code', 'starts_at', 'ends_at', 'closed'];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'closed' => 'boolean',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'period_id');
    }
}
