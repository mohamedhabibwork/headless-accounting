<?php

declare(strict_types=1);

use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Models\WarehouseZone;

describe('WarehouseBin location coordinates', function () {

    it('stores aisle/rack/shelf/level/position and computes full coordinate', function () {
        $warehouse = Warehouse::factory()->create();
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);
        $bin = WarehouseBin::factory()->create([
            'zone_id' => $zone->id,
            'code' => 'X-1',
            'aisle' => 'A1',
            'rack' => 'R1',
            'shelf' => 'S1',
            'level' => 'L1',
            'position' => 'P1',
        ]);

        $fresh = $bin->fresh();
        expect($fresh->aisle)->toBe('A1');
        expect($fresh->rack)->toBe('R1');
        expect($fresh->shelf)->toBe('S1');
        expect($fresh->level)->toBe('L1');
        expect($fresh->position)->toBe('P1');
        expect($fresh->fullCoordinate())->toBe('A1/R1/S1/L1/P1');
    });

    it('tracks current_units and reports available capacity', function () {
        $warehouse = Warehouse::factory()->create();
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);
        $bin = WarehouseBin::factory()->create([
            'zone_id' => $zone->id,
            'code' => 'X-2',
            'capacity_units' => 100,
            'current_units' => 40,
        ]);

        $fresh = $bin->fresh();
        expect($fresh->availableCapacityUnits())->toBe(60.0);
    });

    it('tracks bin↔stock linkage via StockItem.bin_id', function () {
        $warehouse = Warehouse::factory()->create(['code' => 'WH-BIN']);
        $location = Location::create(['code' => 'WH-BIN-LOC', 'name' => 'Bin Loc', 'active' => true]);
        $warehouse->update(['location_id' => $location->id]);
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);
        $bin = WarehouseBin::factory()->create(['zone_id' => $zone->id, 'code' => 'X-3']);

        $variant = ProductVariant::factory()->create();

        $prefix = config('headless-accounting.table_prefix', 'ha_');
        DB::table($prefix.'stock_items')->insert([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'bin_id' => $bin->id,
            'on_hand' => 5,
            'reserved' => 0,
            'incoming' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $si = StockItem::query()->where('variant_id', $variant->id)->where('location_id', $location->id)->first();
        expect((int) $si->bin_id)->toBe($bin->id);
    });
});
