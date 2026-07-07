<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WarehousePrice — per-warehouse price override for a {@see ProductVariant}.
 * Each row carries its own currency, minimum quantity and effective date range.
 */
class WarehousePrice extends BaseModel
{
    use HasFactory;

    protected string $tableSuffix = 'warehouse_prices';

    protected $fillable = [
        'warehouse_id', 'variant_id',
        'currency', 'amount_minor',
        'min_quantity', 'tax_inclusive',
        'effective_from', 'effective_until',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'min_quantity' => 'float',
        'tax_inclusive' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function scopeEffective($query, CarbonInterface $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date);
        });
    }
}
