<?php

declare(strict_types=1);

use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Inventory\ReplenishmentService;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Tenancy\Company;

describe('ReplenishmentService', function () {

    it('detects a variant below reorder_point and returns a proposal', function () {
        $company = Company::create([
            'code' => 'REP',
            'name' => 'Replenish Co',
            'base_currency' => 'EUR',
        ]);
        $location = Location::create(['code' => 'WH-REP', 'name' => 'Replenish WH', 'active' => true]);
        $warehouse = Warehouse::factory()->create([
            'code' => 'WH-REP-WH',
            'company_id' => $company->id,
            'location_id' => $location->id,
        ]);
        $variant = ProductVariant::factory()->create([
            'reorder_point' => 20,
            'max_stock' => 100,
            'reorder_quantity' => 50,
        ]);

        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'on_hand' => 5,
        ]);

        $proposals = app(ReplenishmentService::class)->proposals($company->id);

        expect($proposals->count())->toBeGreaterThan(0);
        $first = $proposals->first();
        expect($first['variant_id'])->toBe($variant->id);
        expect($first['warehouse_id'])->toBe($warehouse->id);
        expect($first['current_on_hand'])->toBe(5);
        expect($first['suggested_quantity'])->toBeGreaterThan(0);
    });

    it('returns empty proposals when all stock above reorder_point', function () {
        $company = Company::create([
            'code' => 'REP2-'.uniqid(),
            'name' => 'Replenish Co 2',
            'base_currency' => 'EUR',
        ]);
        $location = Location::create(['code' => 'WH-REP2', 'name' => 'Replenish WH 2', 'active' => true]);
        Warehouse::factory()->create([
            'code' => 'WH-REP2-WH',
            'company_id' => $company->id,
            'location_id' => $location->id,
        ]);
        $variant = ProductVariant::factory()->create([
            'reorder_point' => 10,
            'reorder_quantity' => 30,
        ]);

        app(InventoryValuationService::class)->receipt($variant, $location->id, 50, 100, 'EUR');

        $proposals = app(ReplenishmentService::class)->proposals($company->id);

        expect($proposals->count())->toBe(0);
    });
});
