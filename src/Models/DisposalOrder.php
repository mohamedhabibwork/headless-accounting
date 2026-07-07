<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DisposalOrder — header that groups one or more {@see StockWriteOff}
 * rows into a single disposal workflow (scrap, recycle, return to vendor,
 * donate, destroy, sell).
 */
class DisposalOrder extends BaseModel
{
    use BelongsToCompany, HasFactory;

    public const METHOD_SCRAP = 'scrap';

    public const METHOD_RECYCLE = 'recycle';

    public const METHOD_RETURN_TO_VENDOR = 'return_to_vendor';

    public const METHOD_DONATE = 'donate';

    public const METHOD_DESTROY = 'destroy';

    public const METHOD_SELL = 'sell';

    public const STATE_DRAFT = 'draft';

    public const STATE_APPROVED = 'approved';

    public const STATE_EXECUTED = 'executed';

    public const STATE_CANCELLED = 'cancelled';

    protected string $tableSuffix = 'disposal_orders';

    protected $fillable = [
        'company_id', 'number',
        'method', 'disposed_at', 'state',
        'reason', 'notes',
        'journal_entry_id',
    ];

    protected $casts = [
        'disposed_at' => 'date',
    ];

    public function writeOffs(): HasMany
    {
        return $this->hasMany(StockWriteOff::class, 'disposal_order_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
