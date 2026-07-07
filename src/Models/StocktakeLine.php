<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\HR\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StocktakeLine — per (variant, bin) count row. The package keeps
 * independent counters per bin so multi-bin SKUs can be verified
 * at each location.
 */
class StocktakeLine extends BaseModel
{
    use HasFactory;

    public const STATE_PENDING = 'pending';

    public const STATE_COUNTED = 'counted';

    public const STATE_RECOUNT = 'recount';

    public const STATE_APPROVED = 'approved';

    public const STATE_REJECTED = 'rejected';

    protected string $tableSuffix = 'stocktake_lines';

    protected $fillable = [
        'stocktake_id', 'variant_id', 'bin_id',
        'system_quantity', 'counted_quantity', 'variance',
        'unit_cost_minor', 'variance_value_minor', 'currency',
        'state', 'count_round', 'reason',
        'counter_id', 'counted_at',
    ];

    protected $casts = [
        'system_quantity' => 'integer',
        'counted_quantity' => 'integer',
        'variance' => 'integer',
        'unit_cost_minor' => 'integer',
        'variance_value_minor' => 'integer',
        'count_round' => 'integer',
        'counted_at' => 'datetime',
    ];

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class, 'stocktake_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'counter_id');
    }

    public function isShort(): bool
    {
        return $this->variance < 0;
    }

    public function isOver(): bool
    {
        return $this->variance > 0;
    }

    public function isMatched(): bool
    {
        return $this->variance === 0;
    }

    public function hasVariance(): bool
    {
        return $this->variance !== 0;
    }
}
