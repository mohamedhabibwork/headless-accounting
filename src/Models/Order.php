<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Models\Concerns\RecordsEvents;
use Headless\Accounting\States\OrderStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends BaseModel implements Payable
{
    use HasFactory, RecordsEvents, SoftDeletes;

    public const STATE_CART = 'cart';

    public const STATE_DRAFT = 'draft';

    public const STATE_PLACED = 'placed';

    public const STATE_PAID = 'paid';

    public const STATE_PARTIALLY_FULFILLED = 'partially_fulfilled';

    public const STATE_FULFILLED = 'fulfilled';

    public const STATE_CLOSED = 'closed';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_REFUNDED = 'refunded';

    protected string $tableSuffix = 'orders';

    protected $fillable = [
        'number', 'customer_id', 'channel_code',
        'currency', 'fx_currency', 'fx_rate',
        'state',
        'subtotal_minor', 'tax_total_minor',
        'shipping_minor', 'discount_total_minor',
        'grand_total_minor', 'item_count',
        'locale', 'email',
        'billing_address_snapshot', 'shipping_address_snapshot',
        'metadata',
        'placed_at', 'paid_at', 'fulfilled_at',
        'closed_at', 'cancelled_at',
    ];

    protected $casts = [
        'subtotal_minor' => 'integer',
        'tax_total_minor' => 'integer',
        'shipping_minor' => 'integer',
        'discount_total_minor' => 'integer',
        'grand_total_minor' => 'integer',
        'item_count' => 'integer',
        'fx_rate' => 'float',
        'billing_address_snapshot' => 'array',
        'shipping_address_snapshot' => 'array',
        'metadata' => 'array',
        'placed_at' => 'datetime',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** — Payable — */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function totalDue(): int
    {
        return (int) $this->grand_total_minor;
    }

    public function totalPaid(): int
    {
        if (array_key_exists('paid_sum_minor', $this->attributes)) {
            return (int) $this->attributes['paid_sum_minor'];
        }

        return (int) $this->payments()
            ->where('state', Payment::STATE_CAPTURED)
            ->sum('amount_minor');
    }

    public function balanceDue(): int
    {
        return max(0, $this->totalDue() - $this->totalPaid());
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /** — Relations — */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(OrderStateTransition::class)->orderByDesc('id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stateMachine(): OrderStateMachine
    {
        return new OrderStateMachine($this);
    }
}
