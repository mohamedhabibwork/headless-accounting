<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends BaseModel
{
    protected string $tableSuffix = 'stock_movements';

    protected $fillable = [
        'stock_item_id', 'reason', 'quantity', 'balance_after',
        'source_type', 'source_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
