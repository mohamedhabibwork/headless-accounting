<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends BaseModel
{
    protected string $tableSuffix = 'cart_items';

    protected $fillable = [
        'cart_id', 'variant_id', 'quantity',
        'unit_price_minor', 'currency', 'adjustments',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'adjustments' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function lineTotal(): int
    {
        return (int) $this->unit_price_minor * (int) $this->quantity;
    }
}
