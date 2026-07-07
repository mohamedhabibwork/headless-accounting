<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\CostCenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GoodsIssue — outbound issue (consumption, sampling, damage, write-off)
 * for a warehouse / location. When the document is posted, the matching
 * {@see JournalEntry} is recorded on `journal_entry_id` and the stock
 * movements + cost-centre allocations are written.
 */
class GoodsIssue extends BaseModel
{
    use BelongsToCompany, HasFactory;

    public const REASON_SALES = 'sales';

    public const REASON_CONSUMPTION = 'consumption';

    public const REASON_SAMPLING = 'sampling';

    public const REASON_DAMAGE = 'damage';

    public const REASON_LOSS = 'loss';

    public const REASON_TRANSFER = 'transfer';

    public const REASON_PRODUCTION = 'production';

    public const REASON_OTHER = 'other';

    public const STATE_DRAFT = 'draft';

    public const STATE_APPROVED = 'approved';

    public const STATE_POSTED = 'posted';

    public const STATE_CANCELLED = 'cancelled';

    protected string $tableSuffix = 'goods_issues';

    protected $fillable = [
        'company_id', 'warehouse_id', 'number',
        'reason', 'issued_at', 'state',
        'cost_center_id', 'project_id',
        'lines', 'notes', 'journal_entry_id',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'lines' => 'array',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'warehouse_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
