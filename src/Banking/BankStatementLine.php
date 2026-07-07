<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankStatementLine extends BaseModel
{
    protected string $tableSuffix = 'bank_statement_lines';

    protected $fillable = [
        'reconciliation_id', 'date', 'description',
        'amount_minor', 'reference',
        'matched_to_type', 'matched_to_id', 'match_state',
    ];

    protected $casts = [
        'date' => 'date',
        'amount_minor' => 'integer',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function matchedTo(): MorphTo
    {
        return $this->morphTo();
    }
}
