<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends BaseModel
{
    protected string $tableSuffix = 'payment_refunds';

    protected $fillable = [
        'payment_id', 'amount_minor', 'currency',
        'provider_refund_id', 'reason',
        'provider_response',
    ];

    protected $casts = ['amount_minor' => 'integer', 'provider_response' => 'array'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
