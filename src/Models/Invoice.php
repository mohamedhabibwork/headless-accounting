<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Models\Concerns\RecordsEvents;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel implements Payable
{
    use RecordsEvents, SoftDeletes;

    public const STATE_DRAFT = 'draft';

    public const STATE_ISSUED = 'issued';

    public const STATE_PAID = 'paid';

    public const STATE_PARTIAL = 'partial';

    public const STATE_VOID = 'void';

    public const STATE_CANCELLED = 'cancelled';

    protected string $tableSuffix = 'invoices';

    protected $fillable = [
        'number', 'order_id', 'customer_id', 'currency',
        'state',
        'subtotal_minor', 'tax_total_minor',
        'grand_total_minor', 'paid_minor', 'balance_minor',
        'issued_at', 'due_at', 'lines',
    ];

    protected $casts = [
        'subtotal_minor' => 'integer',
        'tax_total_minor' => 'integer',
        'grand_total_minor' => 'integer',
        'paid_minor' => 'integer',
        'balance_minor' => 'integer',
        'issued_at' => 'date',
        'due_at' => 'date',
        'lines' => 'array',
    ];

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function totalDue(): int
    {
        return (int) $this->balance_minor;
    }

    public function totalPaid(): int
    {
        return (int) $this->paid_minor;
    }

    public function balanceDue(): int
    {
        return (int) $this->balance_minor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }
}
