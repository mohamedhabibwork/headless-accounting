<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockItem extends BaseModel
{
    protected string $tableSuffix = 'stock_items';

    protected $fillable = [
        'variant_id', 'location_id', 'bin_id',
        'on_hand', 'reserved', 'incoming',
        'min_stock', 'max_stock', 'reorder_point',
        'average_cost_minor', 'currency',
    ];

    protected $casts = [
        'on_hand' => 'integer',
        'reserved' => 'integer',
        'incoming' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'reorder_point' => 'integer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stock_item_id');
    }

    public function reservations(): MorphMany
    {
        return $this->morphMany(StockReservation::class, 'source');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(BatchStock::class, 'location_id', 'location_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'location_id', 'location_id');
    }

    public function available(): int
    {
        return max(0, $this->on_hand - $this->reserved);
    }

    public function availableCapacityForReorder(): int
    {
        return max(0, (int) $this->max_stock - (int) $this->on_hand);
    }
}
