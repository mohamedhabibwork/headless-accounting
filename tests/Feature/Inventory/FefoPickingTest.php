<?php

declare(strict_types=1);

use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Inventory\BatchService;
use Headless\Accounting\Models\BatchStock;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Models\WarehouseZone;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    Company::create(['code' => 'FEFO', 'name' => 'FEFO Co', 'base_currency' => 'EUR']);
});

describe('FEFO picking', function () {

    it('picks the earliest-expiring batch first', function () {
        $variant = ProductVariant::factory()->create(['batch_tracked' => true]);
        $location = Location::create(['code' => 'WH-FEFO2', 'name' => 'FEFO WH 2', 'active' => true]);
        $warehouse = Warehouse::factory()->create();
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);
        $bin = WarehouseBin::factory()->create(['zone_id' => $zone->id]);

        $svc = app(BatchService::class);
        $batchEarly = $svc->create(
            variantId: $variant->id,
            batchNumber: 'B-FEFO-E',
            manufacturingDate: today(),
            expirationDate: today()->addDays(10),
        );
        $batchLate = $svc->create(
            variantId: $variant->id,
            batchNumber: 'B-FEFO-L',
            manufacturingDate: today(),
            expirationDate: today()->addDays(60),
        );

        $svc->receive($batchEarly, $location->id, $bin->id, 5, 100, 'EUR');
        $svc->receive($batchLate, $location->id, $bin->id, 5, 200, 'EUR');

        $svc->consumeFefo($variant, $location->id, 3);

        $earlyQty = (int) BatchStock::query()->where('batch_id', $batchEarly->id)->where('location_id', $location->id)->value('quantity');
        $lateQty = (int) BatchStock::query()->where('batch_id', $batchLate->id)->where('location_id', $location->id)->value('quantity');

        expect($earlyQty)->toBe(2);
        expect($lateQty)->toBe(5);
    });

    it('throws when total available < requested qty', function () {
        $variant = ProductVariant::factory()->create(['batch_tracked' => true]);
        $location = Location::create(['code' => 'WH-FEFO3', 'name' => 'FEFO WH 3', 'active' => true]);
        $warehouse = Warehouse::factory()->create();
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);
        $bin = WarehouseBin::factory()->create(['zone_id' => $zone->id]);

        $svc = app(BatchService::class);
        $batch = $svc->create(
            variantId: $variant->id,
            batchNumber: 'B-FEFO-1',
            manufacturingDate: today(),
            expirationDate: today()->addDays(30),
        );
        $svc->receive($batch, $location->id, $bin->id, 5, 100, 'EUR');

        expect(fn () => $svc->consumeFefo($variant, $location->id, 10))
            ->toThrow(AccountingException::class);
    });
});
