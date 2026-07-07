<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseBin extends BaseModel
{
    use HasFactory;

    protected string $tableSuffix = 'warehouse_bins';

    protected $fillable = [
        'zone_id', 'code', 'barcode',
        'aisle', 'rack', 'shelf', 'level', 'position',
        'qr_code',
        'capacity_units', 'current_units',
        'max_weight_grams', 'current_weight_grams',
        'active',
    ];

    protected $casts = [
        'capacity_units' => 'float',
        'current_units' => 'float',
        'max_weight_grams' => 'float',
        'current_weight_grams' => 'float',
        'active' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'zone_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->zone?->warehouse();
    }

    public function batchStocks(): HasMany
    {
        return $this->hasMany(BatchStock::class, 'bin_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'bin_id');
    }

    public function fullPath(): string
    {
        return implode(' / ', array_filter([
            $this->zone?->warehouse?->code,
            $this->zone?->code,
            $this->code,
        ]));
    }

    public function fullCoordinate(): string
    {
        return implode('/', array_filter([
            $this->aisle,
            $this->rack,
            $this->shelf,
            $this->level,
            $this->position,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function availableCapacityUnits(): float
    {
        return (float) $this->capacity_units - (float) $this->current_units;
    }

    public function availableCapacityWeightGrams(): float
    {
        return (float) $this->max_weight_grams - (float) $this->current_weight_grams;
    }

    public function scopeForVariant($query, int $variantId)
    {
        return $query->where(function ($q) use ($variantId) {
            $q->whereHas('batchStocks.batch', fn ($q2) => $q2->where('variant_id', $variantId))
                ->orWhereHas('serialNumbers', fn ($q2) => $q2->where('variant_id', $variantId));
        });
    }
}
