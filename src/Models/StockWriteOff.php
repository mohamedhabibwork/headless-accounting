<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockWriteOff — damaged / lost / expired items leaving inventory.
 *
 * Lifecycle: pending → approved → disposed (or cancelled). When disposed
 * the write-off is attached to a {@see DisposalOrder}; when posted the
 * matching {@see JournalEntry} is recorded on `journal_entry_id`.
 */
class StockWriteOff extends BaseModel
{
    use BelongsToCompany, HasFactory;

    public const CATEGORY_DAMAGED = 'damaged';

    public const CATEGORY_LOST = 'lost';

    public const CATEGORY_EXPIRED = 'expired';

    public const CATEGORY_OBSOLETE = 'obsolete';

    public const CATEGORY_STOLEN = 'stolen';

    public const CATEGORY_RECALLED = 'recalled';

    public const STATE_PENDING = 'pending';

    public const STATE_APPROVED = 'approved';

    public const STATE_DISPOSED = 'disposed';

    public const STATE_CANCELLED = 'cancelled';

    protected string $tableSuffix = 'stock_write_offs';

    protected $fillable = [
        'company_id', 'warehouse_id', 'number',
        'category', 'occurred_at', 'state',
        'lines', 'notes',
        'disposal_order_id', 'journal_entry_id',
    ];

    protected $casts = [
        'occurred_at' => 'date',
        'lines' => 'array',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'warehouse_id');
    }

    public function disposalOrder(): BelongsTo
    {
        return $this->belongsTo(DisposalOrder::class, 'disposal_order_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
