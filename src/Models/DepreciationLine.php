<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationLine extends BaseModel
{
    protected string $tableSuffix = 'depreciation_lines';

    protected $fillable = [
        'asset_id', 'period',
        'amount_minor', 'currency',
        'accumulated_minor', 'book_value_minor',
        'fiscal_year', 'journal_entry_id', 'posted',
    ];

    protected $casts = [
        'period' => 'date',
        'amount_minor' => 'integer',
        'accumulated_minor' => 'integer',
        'book_value_minor' => 'integer',
        'fiscal_year' => 'integer',
        'posted' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
