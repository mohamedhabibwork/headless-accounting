<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAdjustment extends BaseModel
{
    protected string $tableSuffix = 'order_adjustments';

    protected $fillable = [
        'order_id', 'order_item_id', 'discount_id',
        'type', 'name', 'amount_minor', 'currency',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
