<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseLine extends BaseModel
{
    protected string $tableSuffix = 'expense_lines';

    protected $fillable = [
        'claim_id', 'account_id', 'date', 'description',
        'amount_minor', 'currency', 'tax_percent',
        'tax_rate_id', 'mileage_km', 'vehicle_id', 'receipt_url',
    ];

    protected $casts = [
        'date' => 'date',
        'amount_minor' => 'integer',
        'mileage_km' => 'float',
        'tax_percent' => 'float',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'claim_id');
    }
}
