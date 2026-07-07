<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Enums\Inventory\BatchStatus;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Batch — a lot/batch master record for a {@see ProductVariant}.
 *
 * Carries manufacturing + expiration dates, supplier/production batch numbers,
 * and a status that the warehouse workflow can advance through
 * (active → quarantined → recalled → depleted → expired).
 *
 * Physical on-hand quantity for the batch is tracked at {@see BatchStock}.
 */
class Batch extends BaseModel
{
    use BelongsToCompany, HasFactory;

    protected string $tableSuffix = 'batches';

    protected $fillable = [
        'company_id', 'variant_id',
        'batch_number', 'supplier_batch_number', 'production_batch_number',
        'manufacturing_date', 'expiration_date',
        'status', 'attributes', 'notes',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiration_date' => 'date',
        'attributes' => 'array',
        'status' => BatchStatus::class,
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function batchStocks(): HasMany
    {
        return $this->hasMany(BatchStock::class, 'batch_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'batch_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', BatchStatus::Active);
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', BatchStatus::Expired)
                ->orWhereDate('expiration_date', '<', now()->toDateString());
        });
    }

    public function scopeNearExpiry($query, int $days = 30)
    {
        return $query->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', now()->toDateString())
            ->whereDate('expiration_date', '<=', now()->addDays($days)->toDateString());
    }

    public function isExpired(): bool
    {
        if ($this->status === BatchStatus::Expired) {
            return true;
        }

        return $this->expiration_date !== null
            && $this->expiration_date->lt(now()->startOfDay());
    }

    public function isNearExpiry(int $days = 30): bool
    {
        if ($this->expiration_date === null) {
            return false;
        }

        $today = now()->startOfDay();
        $cutoff = $today->copy()->addDays($days);

        return $this->expiration_date->between($today, $cutoff);
    }

    public function daysToExpiry(): ?int
    {
        if ($this->expiration_date === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expiration_date->startOfDay(), false);
    }

    public function quantityOnHand(): int
    {
        return (int) $this->batchStocks()->sum('quantity');
    }
}
