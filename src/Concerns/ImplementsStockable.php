<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * ImplementsStockable — drop-in trait for any host-side model that
 * represents an inventory item but is not part of the package's
 * `Product` / `ProductVariant` tables.
 *
 * Schema expected:
 *
 *     $table->boolean('stock_tracked')->default(true);
 *     $table->boolean('batch_tracked')->default(false);
 *     $table->boolean('serial_tracked')->default(false);
 *     $table->unsignedInteger('safety_stock')->default(0);
 *
 * The trait also wires the package's polymorphic `StockItem` and
 * `StockMovement` relations when those models exist on the host's
 * database. If your host project does not store inventory through
 * the package, simply ignore those relations — the `isStockTracked()`
 * flag will already gate which items the inventory subsystem touches.
 *
 * @mixin Model
 */
trait ImplementsStockable
{
    /** Whether this item has inventory tracked by the package. */
    public function isStockTracked(): bool
    {
        return (bool) ($this->attributes[$this->stockTrackedAttribute()] ?? true);
    }

    public function isBatchTracked(): bool
    {
        return (bool) ($this->attributes[$this->batchTrackedAttribute()] ?? false);
    }

    public function isSerialTracked(): bool
    {
        return (bool) ($this->attributes[$this->serialTrackedAttribute()] ?? false);
    }

    public function safetyStock(): int
    {
        return (int) ($this->attributes[$this->safetyStockAttribute()] ?? 0);
    }

    /**
     * Polymorphic `StockItem` rows (one per warehouse / location). Returns
     * an empty MorphMany when the package's StockItem model isn't
     * installed in the host project, so the trait stays well-behaved.
     */
    public function stockItems(): MorphMany
    {
        return $this->morphMany(
            StockItem::class,
            'subject'
        );
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(
            StockMovement::class,
            'subject'
        );
    }

    protected function stockTrackedAttribute(): string
    {
        return property_exists($this, 'stockTrackedAttribute')
            ? $this->stockTrackedAttribute
            : 'stock_tracked';
    }

    protected function batchTrackedAttribute(): string
    {
        return property_exists($this, 'batchTrackedAttribute')
            ? $this->batchTrackedAttribute
            : 'batch_tracked';
    }

    protected function serialTrackedAttribute(): string
    {
        return property_exists($this, 'serialTrackedAttribute')
            ? $this->serialTrackedAttribute
            : 'serial_tracked';
    }

    protected function safetyStockAttribute(): string
    {
        return property_exists($this, 'safetyStockAttribute')
            ? $this->safetyStockAttribute
            : 'safety_stock';
    }
}
