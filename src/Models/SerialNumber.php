<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Enums\Inventory\SerialNumberStatus;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SerialNumber — one row per serial-tracked item with full status +
 * assignment history. Statuses trace the full lifecycle from receipt
 * (in_stock) through reservation, sale, return, repair and retirement.
 *
 * Every transition is also recorded as a {@see SerialEvent} for an
 * audit trail.
 */
class SerialNumber extends BaseModel
{
    use BelongsToCompany, HasFactory;

    protected string $tableSuffix = 'serial_numbers';

    protected $fillable = [
        'company_id', 'variant_id', 'batch_id',
        'serial', 'status',
        'location_id', 'bin_id',
        'manufacturing_date', 'warranty_expires_at', 'sold_at',
        'assigned_to_customer_id',
        'warranty_terms', 'attributes',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'warranty_expires_at' => 'date',
        'sold_at' => 'date',
        'warranty_terms' => 'array',
        'attributes' => 'array',
        'status' => SerialNumberStatus::class,
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'assigned_to_customer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SerialEvent::class, 'serial_number_id')->orderBy('occurred_at', 'desc');
    }

    public function scopeInStock($query)
    {
        return $query->where('status', SerialNumberStatus::InStock);
    }

    public function scopeAssignedTo($query, int $customerId)
    {
        return $query->where('assigned_to_customer_id', $customerId);
    }

    public function isUnderWarranty(): bool
    {
        if ($this->warranty_expires_at === null) {
            return false;
        }

        return $this->warranty_expires_at->gte(now()->toDateString());
    }
}
