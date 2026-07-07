<?php

declare(strict_types=1);

use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Models\WarehouseZone;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('Warehouse model', function () {
    it('creates a warehouse with defaults and exposes zones / bins relations', function () {
        $warehouse = Warehouse::factory()->create([
            'code' => 'WH1',
            'name' => 'Main DC',
        ]);

        expect($warehouse->code)->toBe('WH1');
        expect($warehouse->fulfillment_enabled)->toBeTrue();
        expect($warehouse->stocktake_enabled)->toBeTrue();
        expect($warehouse->zones())->toBeInstanceOf(HasMany::class);
    });

    it('links a warehouse to a location and exposes its stock items', function () {
        $location = Location::create(['code' => 'WH1-LOC', 'name' => 'Main', 'active' => true]);
        $warehouse = Warehouse::factory()->create([
            'code' => 'WH1',
            'name' => 'Main DC',
            'location_id' => $location->id,
        ]);

        expect($warehouse->location->is($location))->toBeTrue();

        $variant = ProductVariant::factory()->create();
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'on_hand' => 12,
        ]);

        expect((int) $warehouse->stockItems()->sum('on_hand'))->toBe(12);
    });

    it('returns null when no coordinates are set for distance calc', function () {
        $warehouse = Warehouse::factory()->create();
        expect($warehouse->distanceKmFrom(48.85, 2.35))->toBeNull();
    });

    it('computes the great-circle distance between two points', function () {
        $warehouse = Warehouse::factory()->at('Paris', 'FR', 48.8566, 2.3522)->create();
        $km = $warehouse->distanceKmFrom(51.5074, -0.1278);
        // Paris → London ≈ 343 km
        expect($km)->toBeGreaterThan(300.0);
        expect($km)->toBeLessThan(400.0);
    });

    it('reports the default pick and pack zones', function () {
        $warehouse = Warehouse::factory()->create();
        $pickZone = WarehouseZone::factory()->pickFace()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'PICK',
        ]);
        $packZone = WarehouseZone::factory()->packing()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'PACK',
        ]);

        expect($warehouse->defaultPickZone()?->is($pickZone))->toBeTrue();
        expect($warehouse->defaultPackZone()?->is($packZone))->toBeTrue();
    });
});

describe('WarehouseZone + WarehouseBin', function () {
    it('belongs to a warehouse and exposes its bins', function () {
        $warehouse = Warehouse::factory()->create();
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);

        WarehouseBin::factory()->create(['zone_id' => $zone->id, 'code' => 'A-01-01']);
        WarehouseBin::factory()->create(['zone_id' => $zone->id, 'code' => 'A-01-02']);

        expect($zone->bins()->count())->toBe(2);
    });

    it('builds a full bin path string', function () {
        $warehouse = Warehouse::factory()->create(['code' => 'PARIS']);
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id, 'code' => 'PICK']);
        $bin = WarehouseBin::factory()->create(['zone_id' => $zone->id, 'code' => 'A-01-01']);

        expect($bin->fullPath())->toBe('PARIS / PICK / A-01-01');
    });
});

describe('Warehouse capabilities', function () {
    it('returns capability flags from the JSON column', function () {
        $warehouse = Warehouse::factory()->create([
            'capabilities' => ['hazmat' => false, 'cold_chain' => true, 'oversized' => true],
        ]);

        expect($warehouse->supports('cold_chain'))->toBeTrue();
        expect($warehouse->supports('hazmat'))->toBeFalse();
        expect($warehouse->supports('oversized'))->toBeTrue();
    });
});
