<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Inventory\ReceiveGoods;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Batch;
use Headless\Accounting\Models\BatchStock;
use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Models\WarehouseZone;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    $this->installChart();
    Company::create(['code' => 'R', 'name' => 'Receive Co', 'base_currency' => 'EUR']);
});

describe('ReceiveGoods action', function () {

    it('creates cost layer, stock movement, and journal entry on plain receipt', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH1', 'name' => 'Main WH', 'active' => true]);

        $result = app(ReceiveGoods::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantity: 10,
            unitCostMinor: 2500,
            currency: 'EUR',
        );

        expect(CostLayer::query()->where('variant_id', $variant->id)->where('location_id', $location->id)->exists())->toBeTrue();

        $movement = StockMovement::query()->where('reason', 'receipt')->latest('id')->first();
        expect($movement)->not->toBeNull();
        expect((int) $movement->quantity)->toBe(10);

        $stockItem = StockItem::query()->where('variant_id', $variant->id)->where('location_id', $location->id)->first();
        expect((int) $stockItem->on_hand)->toBe(10);

        expect($result['journal_entry'])->toBeInstanceOf(JournalEntry::class);
        $result['journal_entry']->assertBalanced();
    });

    it('also creates a Batch and BatchStock when batchNumber and binId are provided', function () {
        $variant = ProductVariant::factory()->create(['batch_tracked' => true]);
        $location = Location::create(['code' => 'WH2', 'name' => 'WH 2', 'active' => true]);
        $warehouse = Warehouse::factory()->create();
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);
        $bin = WarehouseBin::factory()->create(['zone_id' => $zone->id]);

        $result = app(ReceiveGoods::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantity: 25,
            unitCostMinor: 1500,
            currency: 'EUR',
            batchNumber: 'B-001',
            manufacturingDate: today(),
            expirationDate: today()->addDays(60),
            binId: $bin->id,
        );

        expect($result['batch'])->toBeInstanceOf(Batch::class);
        expect($result['batch']->batch_number)->toBe('B-001');

        $batchStock = BatchStock::query()->where('batch_id', $result['batch']->id)->where('location_id', $location->id)->first();
        expect($batchStock)->not->toBeNull();
        expect((int) $batchStock->quantity)->toBe(25);
    });

    it('uses finished_goods account code for item_type=finished_good', function () {
        $product = Product::factory()->create();
        $product->update(['item_type' => 'finished_good']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $location = Location::create(['code' => 'WH3', 'name' => 'WH 3', 'active' => true]);

        $result = app(ReceiveGoods::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantity: 5,
            unitCostMinor: 4000,
            currency: 'EUR',
        );

        $fgCode = config('headless-accounting.accounting.accounts.finished_goods');
        $fgAccountId = Account::query()->where('code', $fgCode)->value('id');

        $debit = (int) $result['journal_entry']->postings()
            ->where('account_id', $fgAccountId)
            ->sum('debit_minor');
        expect($debit)->toBe(5 * 4000);
    });
});
