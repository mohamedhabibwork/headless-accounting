<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PickList — a unit of warehouse work. Created per (warehouse, plan)
 * combination. Holds ordered lines for the picker to walk.
 */
class PickList extends BaseModel
{
    public const STATE_OPEN = 'open';

    public const STATE_ASSIGNED = 'assigned';

    public const STATE_PICKING = 'picking';

    public const STATE_PICKED = 'picked';

    public const STATE_PACKED = 'packed';

    public const STATE_CANCELLED = 'cancelled';

    protected string $tableSuffix = 'pick_lists';

    protected $fillable = [
        'fulfillment_plan_id', 'warehouse_id',
        'number', 'state',
        'picker_name', 'routes',
        'assigned_at', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'routes' => 'array',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function fulfillmentPlan(): BelongsTo
    {
        return $this->belongsTo(FulfillmentPlan::class, 'fulfillment_plan_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PickListLine::class, 'pick_list_id')->orderBy('pick_sequence');
    }

    public function packStation(): HasMany
    {
        return $this->hasMany(PackStation::class, 'pick_list_id');
    }

    public function totalQuantityRequested(): int
    {
        return (int) $this->lines()->sum('quantity_requested');
    }

    public function totalQuantityPicked(): int
    {
        return (int) $this->lines()->sum('quantity_picked');
    }

    public function isFullyPicked(): bool
    {
        return $this->lines()->where('state', '!=', PickListLine::STATE_PICKED)->doesntExist();
    }

    public function hasShortages(): bool
    {
        return $this->lines()->where('state', PickListLine::STATE_SHORT)->exists();
    }

    public function completionRatio(): float
    {
        $req = $this->totalQuantityRequested();
        if ($req === 0) {
            return 1.0;
        }

        return round($this->totalQuantityPicked() / $req, 4);
    }
}
