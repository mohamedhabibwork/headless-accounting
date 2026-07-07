<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentSchedule extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'payment_schedules';

    protected $fillable = [
        'company_id', 'source_type', 'source_id',
        'installment_no', 'due_date',
        'currency', 'amount_minor',
        'method', 'state',
        'paid_at', 'payment_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
