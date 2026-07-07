<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Inventory\IssueGoods;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Inventory\BatchService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Models\WarehouseZone;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    $this->installChart();
    Company::create(['code' => 'I', 'name' => 'Issue Co', 'base_currency' => 'EUR']);
});

describe('IssueGoods action', function () {

    it('consumes FIFO cost layers and posts COGS', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-ISS', 'name' => 'Iss WH', 'active' => true]);

        $svc = app(InventoryValuationService::class);
        $svc->receipt($variant, $location->id, 10, 100, 'EUR');
        usleep(1_100_000);
        $svc->receipt($variant, $location->id, 5, 200, 'EUR');

        $result = app(IssueGoods::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantity: 12,
        );

        $stockItem = StockItem::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $location->id)
            ->first();
        expect((int) $stockItem->on_hand)->toBe(3);

        $total = 0;
        foreach ($result['consumed_layers'] as $row) {
            $total += ((int) $row['quantity']) * ((int) $row['unit_cost_minor']);
        }
        expect($total)->toBe(10 * 100 + 2 * 200);

        $cogsId = Account::query()->where('code', '5000')->value('id');
        $cogsDebit = (int) $result['journal_entry']->postings()->where('account_id', $cogsId)->sum('debit_minor');
        expect($cogsDebit)->toBe(10 * 100 + 2 * 200);
    });

    it('respects reason=damage and posts to Inventory Damage account', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-DAM', 'name' => 'Dam WH', 'active' => true]);

        $svc = app(InventoryValuationService::class);
        $svc->receipt($variant, $location->id, 5, 100, 'EUR');

        $result = app(IssueGoods::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantity: 2,
            reason: 'damage',
        );

        $total = 0;
        foreach ($result['consumed_layers'] as $row) {
            $total += ((int) $row['quantity']) * ((int) $row['unit_cost_minor']);
        }
        expect($total)->toBe(2 * 100);

        $damageCode = config('headless-accounting.accounting.accounts.inventory_damage');
        $damageAccountId = Account::query()->where('code', $damageCode)->value('id');
        $damageDebit = (int) $result['journal_entry']->postings()->where('account_id', $damageAccountId)->sum('debit_minor');
        expect($damageDebit)->toBe(2 * 100);
    });

    it('FEFO picks earliest-expiring batch for batch-tracked variants', function () {
        $variant = ProductVariant::factory()->create(['batch_tracked' => true]);
        $location = Location::create(['code' => 'WH-FEFO', 'name' => 'FEFO WH', 'active' => true]);
        $warehouse = Warehouse::factory()->create();
        $zone = WarehouseZone::factory()->create(['warehouse_id' => $warehouse->id]);
        $bin = WarehouseBin::factory()->create(['zone_id' => $zone->id]);

        $svc = app(BatchService::class);
        $batchEarly = $svc->create(
            variantId: $variant->id,
            batchNumber: 'B-EARLY',
            manufacturingDate: today(),
            expirationDate: today()->addDays(30),
        );
        $batchLate = $svc->create(
            variantId: $variant->id,
            batchNumber: 'B-LATE',
            manufacturingDate: today(),
            expirationDate: today()->addDays(60),
        );

        $svc->receive($batchEarly, $location->id, $bin->id, 5, 100, 'EUR');
        $svc->receive($batchLate, $location->id, $bin->id, 5, 200, 'EUR');

        $svc->consumeFefo($variant, $location->id, 3);

        $batchEarly = $batchEarly->fresh();
        $batchLate = $batchLate->fresh();

        expect($batchEarly->batchStocks()->where('location_id', $location->id)->sum('quantity'))->toBe(2);
        expect($batchLate->batchStocks()->where('location_id', $location->id)->sum('quantity'))->toBe(5);
    });

    it('throws if insufficient stock', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-SHORT', 'name' => 'Short', 'active' => true]);

        app(InventoryValuationService::class)->receipt($variant, $location->id, 5, 100, 'EUR');

        expect(fn () => app(IssueGoods::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantity: 10,
        ))->toThrow(AccountingException::class);
    });
});
