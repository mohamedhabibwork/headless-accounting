<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Contracts\Stockable;
use Headless\Accounting\Contracts\Taxable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductVariant extends BaseModel implements Stockable, Taxable
{
    use HasFactory;

    protected string $tableSuffix = 'product_variants';

    protected $fillable = [
        'product_id', 'name', 'sku', 'barcode',
        'option_values', 'weight_grams',
        'length_mm', 'width_mm', 'height_mm',
        'stock_tracked', 'active',
        'unit_of_measure', 'batch_tracked', 'serial_tracked', 'expiration_tracked',
        'gs1_gtin', 'hazard_class', 'temperature_class',
        'min_stock', 'max_stock', 'safety_stock',
        'reorder_point', 'reorder_quantity', 'lead_time_days',
    ];

    protected $casts = [
        'option_values' => 'array',
        'stock_tracked' => 'boolean',
        'active' => 'boolean',
        'batch_tracked' => 'boolean',
        'serial_tracked' => 'boolean',
        'expiration_tracked' => 'boolean',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'safety_stock' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'lead_time_days' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'variant_id');
    }

    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'subject');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'variant_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'variant_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'variant_id');
    }

    public function warehouseReorderRules(): HasMany
    {
        return $this->hasMany(WarehouseReorderRule::class, 'variant_id');
    }

    public function warehousePrices(): HasMany
    {
        return $this->hasMany(WarehousePrice::class, 'variant_id');
    }

    public function taxClassId(): ?int
    {
        return $this->product?->tax_class_id;
    }

    public function taxContext(): array
    {
        return $this->product?->taxContext() ?? [];
    }

    public function isStockTracked(): bool
    {
        return (bool) $this->stock_tracked;
    }

    public function isBatchTracked(): bool
    {
        return (bool) $this->batch_tracked;
    }

    public function isSerialTracked(): bool
    {
        return (bool) $this->serial_tracked;
    }

    public function isExpirationTracked(): bool
    {
        return (bool) $this->expiration_tracked;
    }

    public function stockAtOrBelowReorder(): bool
    {
        if ((int) $this->reorder_point <= 0) {
            return false;
        }

        $onHand = (int) $this->stockItems()->sum('on_hand');

        return $onHand <= (int) $this->reorder_point;
    }

    public function suggestedReorderQuantity(): int
    {
        if ((int) $this->reorder_quantity > 0) {
            return (int) $this->reorder_quantity;
        }

        return max((int) $this->max_stock - (int) $this->min_stock, 0);
    }
}
