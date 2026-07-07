<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends BaseModel
{
    protected string $tableSuffix = 'order_items';

    protected $fillable = [
        'order_id', 'variant_id', 'name', 'sku',
        'quantity', 'unit_price_minor',
        'unit_tax_minor', 'currency',
        'tax_rate_percent', 'tax_inclusive',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'tax_inclusive' => 'boolean',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function adjustments()
    {
        return $this->hasMany(OrderAdjustment::class, 'order_item_id');
    }

    public function lineSubtotal(): int
    {
        return (int) $this->unit_price_minor * (int) $this->quantity;
    }

    public function lineTax(): int
    {
        return (int) $this->unit_tax_minor * (int) $this->quantity;
    }
}
