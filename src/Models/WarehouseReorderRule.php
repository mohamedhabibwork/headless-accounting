<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WarehouseReorderRule — per-warehouse override of a {@see ProductVariant}'s
 * default reorder policy (min/max/safety/reorder point + EOQ + lead time).
 * When `automatic_replenishment` is true the warehouse replenishment job
 * generates purchase orders as soon as on-hand drops below `reorder_point`.
 */
class WarehouseReorderRule extends BaseModel
{
    use BelongsToCompany, HasFactory;

    protected string $tableSuffix = 'warehouse_reorder_rules';

    protected $fillable = [
        'company_id', 'warehouse_id', 'variant_id',
        'min_stock', 'max_stock', 'safety_stock',
        'reorder_point', 'reorder_quantity', 'lead_time_days',
        'automatic_replenishment', 'preferred_vendor_code',
    ];

    protected $casts = [
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'safety_stock' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'lead_time_days' => 'integer',
        'automatic_replenishment' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
