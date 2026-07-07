<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Inventory\TransferStock;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\InventoryTransfer;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Tenancy\Company;

describe('TransferStock action', function () {

    beforeEach(function () {
        Company::create(['code' => 'TR-CO-'.uniqid(), 'name' => 'Transfer Co', 'base_currency' => 'EUR']);
    });

    it('moves stock between two locations without GL impact by default', function () {
        $variant = ProductVariant::factory()->create();
        $locationA = Location::create(['code' => 'WH-A', 'name' => 'Warehouse A', 'active' => true]);
        $locationB = Location::create(['code' => 'WH-B', 'name' => 'Warehouse B', 'active' => true]);

        app(InventoryValuationService::class)->receipt($variant, $locationA->id, 10, 100, 'EUR');

        $result = app(TransferStock::class)->execute(
            variant: $variant,
            from: $locationA,
            to: $locationB,
            quantity: 4,
        );

        $stockA = StockItem::query()->where('variant_id', $variant->id)->where('location_id', $locationA->id)->first();
        $stockB = StockItem::query()->where('variant_id', $variant->id)->where('location_id', $locationB->id)->first();
        expect((int) $stockA->on_hand)->toBe(6);
        expect((int) $stockB->on_hand)->toBe(4);

        expect($result['inventory_transfer']->state)->toBe('posted');
        expect($result['inventory_transfer']->journal_entry_id)->toBeNull();
    });

    it('posts inter-company GL when from and to belong to different companies', function () {
        $variant = ProductVariant::factory()->create();
        $companyA = Company::create([
            'code' => 'IC-A-'.uniqid(), 'name' => 'Intercompany A', 'base_currency' => 'EUR',
        ]);
        $companyB = Company::create([
            'code' => 'IC-B-'.uniqid(), 'name' => 'Intercompany B', 'base_currency' => 'EUR',
        ]);

        $warehouseA = Warehouse::factory()->create(['code' => 'WH-IC-A', 'company_id' => $companyA->id]);
        $warehouseB = Warehouse::factory()->create(['code' => 'WH-IC-B', 'company_id' => $companyB->id]);
        $locationA = Location::create(['code' => 'LOC-IC-A', 'name' => 'Loc IC-A', 'active' => true]);
        $locationB = Location::create(['code' => 'LOC-IC-B', 'name' => 'Loc IC-B', 'active' => true]);
        $warehouseA->update(['location_id' => $locationA->id]);
        $warehouseB->update(['location_id' => $locationB->id]);

        $locationA->setAttribute('company_id', $companyA->id);
        $locationB->setAttribute('company_id', $companyB->id);

        app(InventoryValuationService::class)->receipt($variant, $locationA->id, 10, 100, 'EUR');

        $result = app(TransferStock::class)->execute(
            variant: $variant,
            from: $locationA,
            to: $locationB,
            quantity: 4,
        );

        expect($result['inventory_transfer'])->toBeInstanceOf(InventoryTransfer::class);
    });
});
