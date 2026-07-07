<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends BaseModel implements Payable
{
    use BelongsToCompany, SoftDeletes;

    public const STATE_DRAFT = 'draft';

    public const STATE_RECEIVED = 'received';

    public const STATE_PAID = 'paid';

    public const STATE_PARTIAL = 'partial';

    public const STATE_VOID = 'void';

    public const STATE_CANCELLED = 'cancelled';

    protected string $tableSuffix = 'bills';

    protected $fillable = [
        'company_id', 'vendor_id', 'number',
        'currency', 'fx_currency', 'fx_rate',
        'subtotal_minor', 'tax_minor', 'total_minor',
        'paid_minor', 'balance_minor',
        'bill_date', 'due_date', 'state',
        'notes', 'metadata',
    ];

    protected $casts = [
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'paid_minor' => 'integer',
        'balance_minor' => 'integer',
        'fx_rate' => 'float',
        'bill_date' => 'date',
        'due_date' => 'date',
        'metadata' => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function schedules(): MorphMany
    {
        return $this->morphMany(PaymentSchedule::class, 'source');
    }

    // — Payable —
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
        return max(0, (int) $this->balance_minor);
    }

    public function currency(): string
    {
        return $this->currency;
    }
}
