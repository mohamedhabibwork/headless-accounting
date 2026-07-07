<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FulfillmentPlan — the routing plan for an order. Splits an order
 * across one or more {@see Warehouse warehouses} and ranks carrier
 * service options for each leg. Drives pick lists, pack stations, and
 * shipments.
 *
 *   state transitions
 *     planned → allocating → allocated → picking → packed → shipped → delivered
 *                                                ↓
 *                                            cancelled | partial
 *
 * The `allocations` JSON column holds the split (warehouse, variant, qty).
 * The `shipping_options` JSON column holds the ranked carrier quotes.
 */
class FulfillmentPlan extends BaseModel
{
    use BelongsToCompany;

    public const STRATEGY_CHEAPEST = 'cheapest';

    public const STRATEGY_FASTEST = 'fastest';

    public const STRATEGY_CLOSEST = 'closest';

    public const STRATEGY_PRIORITY = 'priority';

    public const STRATEGY_MANUAL = 'manual';

    public const STATE_PLANNED = 'planned';

    public const STATE_ALLOCATING = 'allocating';

    public const STATE_ALLOCATED = 'allocated';

    public const STATE_PICKING = 'picking';

    public const STATE_PACKED = 'packed';

    public const STATE_SHIPPED = 'shipped';

    public const STATE_DELIVERED = 'delivered';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_PARTIAL = 'partial';

    protected string $tableSuffix = 'fulfillment_plans';

    protected $fillable = [
        'company_id', 'order_id', 'number',
        'strategy', 'state',
        'allocations', 'shipping_options', 'metadata',
        'planned_at', 'allocated_at', 'completed_at',
    ];

    protected $casts = [
        'allocations' => 'array',
        'shipping_options' => 'array',
        'metadata' => 'array',
        'planned_at' => 'datetime',
        'allocated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function pickLists(): HasMany
    {
        return $this->hasMany(PickList::class, 'fulfillment_plan_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'fulfillment_plan_id');
    }

    /** Group allocations by warehouse_id. */
    public function allocationsByWarehouse(): array
    {
        $by = [];
        foreach ((array) $this->allocations as $line) {
            $wid = (int) ($line['warehouse_id'] ?? 0);
            $by[$wid] ??= [];
            $by[$wid][] = $line;
        }

        return $by;
    }

    public function totalUnits(): int
    {
        $t = 0;
        foreach ((array) $this->allocations as $line) {
            $t += (int) ($line['quantity'] ?? 0);
        }

        return $t;
    }

    public function totalWeightGrams(): int
    {
        $t = 0;
        foreach ((array) $this->allocations as $line) {
            $t += (int) ($line['weight_grams'] ?? 0) * (int) ($line['quantity'] ?? 0);
        }

        return $t;
    }

    public function chosenShippingOption(): ?array
    {
        $options = (array) $this->shipping_options;
        foreach ($options as $option) {
            if (! empty($option['selected'])) {
                return $option;
            }
        }

        return $options[0] ?? null;
    }
}
