<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\RecordsEvents;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends BaseModel
{
    use RecordsEvents, SoftDeletes;

    public const STATE_AUTHORIZED = 'authorized';

    public const STATE_CAPTURED = 'captured';

    public const STATE_PARTIAL_REFUNDED = 'partially_refunded';

    public const STATE_REFUNDED = 'refunded';

    public const STATE_VOIDED = 'voided';

    public const STATE_FAILED = 'failed';

    public const STATE_PENDING = 'pending';

    protected string $tableSuffix = 'payments';

    protected $fillable = [
        'number', 'payable_type', 'payable_id',
        'currency', 'amount_minor',
        'driver', 'method', 'state',
        'provider_id', 'provider_state',
        'provider_response',
        'authorized_at', 'captured_at',
        'refunded_at', 'voided_at',
        'customer_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'provider_response' => 'array',
        'authorized_at' => 'datetime',
        'captured_at' => 'datetime',
        'refunded_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function amountRefunded(): int
    {
        return (int) $this->refunds()->sum('amount_minor');
    }

    public function amountRefundable(): int
    {
        return max(0, (int) $this->amount_minor - $this->amountRefunded());
    }
}
