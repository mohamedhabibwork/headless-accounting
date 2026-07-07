<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\Branch;
use Headless\Accounting\Tenancy\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Warehouse — a first-class fulfillment location that owns
 *   - its physical stock (via a {@see Location} row when one exists, or
 *     standalone for stores that don't track per-bin inventory)
 *   - a set of {@see WarehouseZone zones} (receiving, storage, pick face, packing, shipping)
 *   - a set of {@see WarehouseBin bins} where SKUs physically sit
 *   - a set of {@see ShippingRateCard rate cards} for carrier rate-shopping
 *
 * The {@see StockItem} model still uses the older {@see Location} table for
 * `location_id`. When a Warehouse has a Location linked, the stock is
 * routed through that location's `stock_items`. The Warehouse is the
 * "fulfillment address" — the Location is the "stock bucket".
 */
class Warehouse extends BaseModel
{
    use BelongsToCompany, HasFactory;

    public const TYPE_WAREHOUSE = 'warehouse';

    public const TYPE_STORE = 'store';

    public const TYPE_DROPSHIP = 'dropship';

    public const TYPE_TRANSIT = 'transit';

    public const TYPE_POP_UP = 'pop_up';

    public const TYPE_CONSIGNMENT = 'consignment';

    public const TYPE_VIRTUAL = 'virtual';

    public const TYPE_QUARANTINE = 'quarantine';

    public const TYPE_RETURNS = 'returns';

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_THREE_PL = 'three_pl';

    protected string $tableSuffix = 'warehouses';

    protected $fillable = [
        'company_id', 'location_id',
        'parent_id', 'branch_id', 'owner_company_id',
        'inter_company', 'consignment', 'virtual',
        'in_transit', 'quarantine_only', 'returns_only',
        'code', 'name', 'type',
        'fulfillment_enabled', 'stocktake_enabled',
        'is_default', 'priority',
        'shipping_address', 'contact', 'capabilities', 'opening_hours',
        'latitude', 'longitude', 'timezone', 'active',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'contact' => 'array',
        'capabilities' => 'array',
        'opening_hours' => 'array',
        'fulfillment_enabled' => 'boolean',
        'stocktake_enabled' => 'boolean',
        'is_default' => 'boolean',
        'active' => 'boolean',
        'inter_company' => 'boolean',
        'consignment' => 'boolean',
        'virtual' => 'boolean',
        'in_transit' => 'boolean',
        'quarantine_only' => 'boolean',
        'returns_only' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'priority' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function ownerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class, 'warehouse_id');
    }

    public function pickZones(): HasMany
    {
        return $this->zones()->where('is_default_pick', true);
    }

    public function packZones(): HasMany
    {
        return $this->zones()->where('is_default_pack', true);
    }

    public function bins(): HasManyThrough
    {
        return $this->hasManyThrough(
            WarehouseBin::class,
            WarehouseZone::class,
            'warehouse_id',
            'zone_id',
            'id',
            'id',
        );
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'location_id', 'location_id');
    }

    public function pickLists(): HasMany
    {
        return $this->hasMany(PickList::class, 'warehouse_id');
    }

    public function stocktakes(): HasMany
    {
        return $this->hasMany(Stocktake::class, 'warehouse_id');
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(ShippingRateCard::class, 'warehouse_id');
    }

    public function reorderRules(): HasMany
    {
        return $this->hasMany(WarehouseReorderRule::class, 'warehouse_id');
    }

    public function warehousePrices(): HasMany
    {
        return $this->hasMany(WarehousePrice::class, 'warehouse_id');
    }

    public function supports(string $capability): bool
    {
        return (bool) ($this->capabilities[$capability] ?? false);
    }

    public function distanceKmFrom(float $lat, float $lng): ?float
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        $earthRadius = 6371.0;
        $latFrom = deg2rad($this->latitude);
        $latTo = deg2rad($lat);
        $latDelta = deg2rad($lat - $this->latitude);
        $lngDelta = deg2rad($lng - $this->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * asin(min(1.0, sqrt($a))));
    }

    public function defaultPickZone(): ?WarehouseZone
    {
        return $this->zones()->where('is_default_pick', true)->first()
            ?? $this->zones()->where('kind', 'pick_face')->first();
    }

    public function defaultPackZone(): ?WarehouseZone
    {
        return $this->zones()->where('is_default_pack', true)->first()
            ?? $this->zones()->where('kind', 'packing')->first();
    }

    public function isVirtual(): bool
    {
        return (bool) $this->virtual || $this->type === self::TYPE_VIRTUAL;
    }

    public function isConsignment(): bool
    {
        return (bool) $this->consignment || $this->type === self::TYPE_CONSIGNMENT;
    }

    public function isQuarantine(): bool
    {
        return (bool) $this->quarantine_only || $this->type === self::TYPE_QUARANTINE;
    }

    public function isReturns(): bool
    {
        return (bool) $this->returns_only || $this->type === self::TYPE_RETURNS;
    }

    public function isInTransit(): bool
    {
        return (bool) $this->in_transit || $this->type === self::TYPE_TRANSIT;
    }

    public function isInterCompany(): bool
    {
        return (bool) $this->inter_company;
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
