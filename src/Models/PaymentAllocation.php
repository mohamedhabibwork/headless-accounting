<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentAllocation extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'payment_allocations';

    protected $fillable = [
        'company_id', 'payment_id', 'target_type', 'target_id',
        'currency', 'amount_minor', 'fx_rate', 'allocated_at',
    ];

    protected $casts = ['amount_minor' => 'integer', 'fx_rate' => 'float', 'allocated_at' => 'date'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
