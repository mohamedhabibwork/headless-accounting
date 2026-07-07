<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends BaseModel
{
    protected string $tableSuffix = 'credit_notes';

    protected $fillable = [
        'number', 'invoice_id', 'amount_minor', 'currency', 'reason',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
