<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRevaluation extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'currency_revaluations';

    protected $fillable = [
        'company_id', 'currency', 'as_of',
        'breakdown', 'journal_entry_id',
    ];

    protected $casts = ['as_of' => 'date', 'breakdown' => 'array'];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
