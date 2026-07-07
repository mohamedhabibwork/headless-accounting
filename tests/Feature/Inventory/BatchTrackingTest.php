<?php

declare(strict_types=1);

use Headless\Accounting\Enums\Inventory\BatchStatus;
use Headless\Accounting\Inventory\BatchService;
use Headless\Accounting\Models\Batch;
use Headless\Accounting\Models\BatchStock;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Models\WarehouseZone;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    Company::create(['code' => 'BAT', 'name' => 'Batch Co', 'base_currency' => 'EUR']);
});

describe('Batch lifecycle', function () {

    it('creates a batch with manufacturing and expiration dates', function () {
        $variant = ProductVariant::factory()->create();

        $batch = app(BatchService::class)->create(
            variantId: $variant->id,
            batchNumber: 'B-NEW-1',
            manufacturingDate: today(),
            expirationDate: today()->addDays(180),
        );

        expect($batch)->toBeInstanceOf(Batch::class);
        expect($batch->status)->toBe(BatchStatus::Active);
        expect($batch->isExpired())->toBeFalse();
        expect($batch->isNearExpiry(30))->toBeFalse();
    });

    it('flags a batch as expired when expiration_date is in the past', function () {
        $variant = ProductVariant::factory()->create();

        $batch = app(BatchService::class)->create(
            variantId: $variant->id,
            batchNumber: 'B-OLD',
            manufacturingDate: today()->subDays(60),
            expirationDate: today()->subDay(),
        );

        expect($batch->isExpired())->toBeTrue();
    });

    it('flags a batch as near expiry within window', function () {
        $variant = ProductVariant::factory()->create();

        $batch = app(BatchService::class)->create(
            variantId: $variant->id,
            batchNumber: 'B-NEAR',
            manufacturingDate: today(),
            expirationDate: today()->addDays(10),
        );

        expect($batch->isNearExpiry(30))->toBeTrue();
        expect($batch->isNearExpiry(5))->toBeFalse();
    });

    it('records quantity via receive and decrements via consumeFefo', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-BAT', 'name' => 'Batch WH', 'active' => true]);
        $warehouse = Warehouse::factory()->create();
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);
        $bin = WarehouseBin::factory()->create(['zone_id' => $zone->id]);

        $svc = app(BatchService::class);
        $batch = $svc->create(
            variantId: $variant->id,
            batchNumber: 'B-RECEIVE',
            manufacturingDate: today(),
            expirationDate: today()->addDays(120),
        );

        $svc->receive(
            batch: $batch,
            locationId: $location->id,
            binId: $bin->id,
            quantity: 100,
            unitCostMinor: 250,
            currency: 'EUR',
        );

        $stock = BatchStock::query()
            ->where('batch_id', $batch->id)
            ->where('location_id', $location->id)
            ->first();
        expect($stock)->not->toBeNull();
        expect((int) $stock->quantity)->toBe(100);

        $svc->consumeFefo($variant, $location->id, 30);

        $stock = $stock->fresh();
        expect((int) $stock->quantity)->toBe(70);
    });
});

describe('BatchService.quarantineExpiredBatches', function () {

    it('finds and quarantines expired batches', function () {
        $variant = ProductVariant::factory()->create();

        $svc = app(BatchService::class);
        $expired = $svc->create(
            variantId: $variant->id,
            batchNumber: 'B-EXP',
            manufacturingDate: today()->subDays(60),
            expirationDate: today()->subDay(),
        );
        $active = $svc->create(
            variantId: $variant->id,
            batchNumber: 'B-OK',
            manufacturingDate: today()->subDays(10),
            expirationDate: today()->addDays(30),
        );

        $count = $svc->quarantineExpiredBatches();

        expect($count)->toBe(1);
        expect($expired->fresh()->status)->toBe(BatchStatus::Expired);
        expect($active->fresh()->status)->toBe(BatchStatus::Active);
    });
});
