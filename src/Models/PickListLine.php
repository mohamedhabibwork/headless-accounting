<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickListLine extends BaseModel
{
    public const STATE_PENDING = 'pending';

    public const STATE_PICKED = 'picked';

    public const STATE_SHORT = 'short';

    public const STATE_SKIPPED = 'skipped';

    protected string $tableSuffix = 'pick_list_lines';

    protected $fillable = [
        'pick_list_id', 'bin_id', 'variant_id', 'stock_item_id',
        'quantity_requested', 'quantity_picked', 'state',
        'note', 'pick_sequence', 'picked_at',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_picked' => 'integer',
        'pick_sequence' => 'integer',
        'picked_at' => 'datetime',
    ];

    public function pickList(): BelongsTo
    {
        return $this->belongsTo(PickList::class, 'pick_list_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function isShort(): bool
    {
        return $this->quantity_picked < $this->quantity_requested;
    }

    public function shortage(): int
    {
        return max(0, (int) $this->quantity_requested - (int) $this->quantity_picked);
    }
}
