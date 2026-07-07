<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Inventory\AdjustInventory;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    $this->installChart();
    Company::create(['code' => 'ADJ', 'name' => 'Adjust Co', 'base_currency' => 'EUR']);
});

describe('AdjustInventory action', function () {

    it('posts gain to Inventory Gain account on positive adjustment', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-GAIN', 'name' => 'Gain WH', 'active' => true]);

        $result = app(AdjustInventory::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantityDelta: 5,
            reason: 'gain',
            unitCostMinor: 200,
        );

        $stockItem = StockItem::query()->where('variant_id', $variant->id)->where('location_id', $location->id)->first();
        expect((int) $stockItem->on_hand)->toBe(5);

        $gainCode = config('headless-accounting.accounting.accounts.inventory_gain');
        $gainAccountId = Account::query()->where('code', $gainCode)->value('id');
        $credit = (int) $result['journal_entry']->postings()->where('account_id', $gainAccountId)->sum('credit_minor');
        expect($credit)->toBe(5 * 200);
    });

    it('posts loss to Inventory Loss account on negative adjustment', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-LOSS', 'name' => 'Loss WH', 'active' => true]);

        app(InventoryValuationService::class)->receipt($variant, $location->id, 10, 100, 'EUR');

        $result = app(AdjustInventory::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantityDelta: -3,
            reason: 'loss',
        );

        $stockItem = StockItem::query()->where('variant_id', $variant->id)->where('location_id', $location->id)->first();
        expect((int) $stockItem->on_hand)->toBe(7);

        $shrinkCode = config('headless-accounting.accounting.accounts.inventory_shrinkage');
        $shrinkAccountId = Account::query()->where('code', $shrinkCode)->value('id');
        $debit = (int) $result['journal_entry']->postings()->where('account_id', $shrinkAccountId)->sum('debit_minor');
        expect($debit)->toBeGreaterThan(0);
    });

    it('writes StockMovement for an adjustment', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-MV', 'name' => 'Movement WH', 'active' => true]);

        app(AdjustInventory::class)->execute(
            variant: $variant,
            warehouse: $location,
            quantityDelta: 7,
            reason: 'gain',
            unitCostMinor: 100,
        );

        $movement = StockMovement::query()
            ->where('reason', 'receipt')
            ->orderByDesc('id')
            ->first();
        expect($movement)->not->toBeNull();
        expect((int) $movement->quantity)->toBe(7);
    });
});
