<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Inventory\PostWriteOff;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockWriteOff;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    $this->installChart();
    Company::create(['code' => 'WO-CO-'.uniqid(), 'name' => 'WO Test Co', 'base_currency' => 'EUR']);
});

describe('Stock write-off', function () {

    it('creates a write-off in pending state', function () {
        $company = Company::create(['code' => 'WO', 'name' => 'WO Co', 'base_currency' => 'EUR']);
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-WO', 'name' => 'WO WH', 'active' => true]);

        app(InventoryValuationService::class)->receipt($variant, $location->id, 10, 100, 'EUR');

        $writeOff = StockWriteOff::create([
            'company_id' => $company->id,
            'warehouse_id' => $location->id,
            'number' => 'WO-TEST-001',
            'category' => StockWriteOff::CATEGORY_DAMAGED,
            'occurred_at' => today()->toDateString(),
            'state' => StockWriteOff::STATE_PENDING,
            'lines' => [
                ['variant_id' => $variant->id, 'quantity' => 3, 'unit_cost_minor' => 100],
            ],
        ]);

        expect($writeOff->state)->toBe(StockWriteOff::STATE_PENDING);
    });

    it('approve flips state from pending to approved', function () {
        $company = Company::create(['code' => 'WO2-'.uniqid(), 'name' => 'WO Co 2', 'base_currency' => 'EUR']);
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-WO2', 'name' => 'WO WH 2', 'active' => true]);

        $writeOff = StockWriteOff::create([
            'company_id' => $company->id,
            'warehouse_id' => $location->id,
            'number' => 'WO-TEST-002',
            'category' => StockWriteOff::CATEGORY_DAMAGED,
            'occurred_at' => today()->toDateString(),
            'state' => StockWriteOff::STATE_PENDING,
            'lines' => [
                ['variant_id' => $variant->id, 'quantity' => 3, 'unit_cost_minor' => 100],
            ],
        ]);

        $writeOff->state = StockWriteOff::STATE_APPROVED;
        $writeOff->save();

        expect($writeOff->fresh()->state)->toBe(StockWriteOff::STATE_APPROVED);
    });

    it('post decrements StockItem and writes a journal entry referencing inventory damage account', function () {
        $company = Company::create(['code' => 'WO3-'.uniqid(), 'name' => 'WO Co 3', 'base_currency' => 'EUR']);
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-WO3', 'name' => 'WO WH 3', 'active' => true]);

        app(InventoryValuationService::class)->receipt($variant, $location->id, 10, 100, 'EUR');

        $writeOff = StockWriteOff::create([
            'company_id' => $company->id,
            'warehouse_id' => $location->id,
            'number' => 'WO-TEST-003',
            'category' => StockWriteOff::CATEGORY_DAMAGED,
            'occurred_at' => today()->toDateString(),
            'state' => StockWriteOff::STATE_APPROVED,
            'lines' => [
                ['variant_id' => $variant->id, 'quantity' => 3, 'unit_cost_minor' => 100],
            ],
        ]);

        $result = app(PostWriteOff::class)->execute($writeOff, 'EUR');

        $stockItem = StockItem::query()->where('variant_id', $variant->id)->where('location_id', $location->id)->first();
        expect((int) $stockItem->on_hand)->toBe(7);

        $writeOff->refresh();
        expect($writeOff->journal_entry_id)->not->toBeNull();

        $damageCode = config('headless-accounting.accounting.accounts.inventory_damage');
        $damageAccountId = Account::query()->where('code', $damageCode)->value('id');
        $entry = JournalEntry::query()->find($writeOff->journal_entry_id);
        $debit = (int) $entry->postings()->where('account_id', $damageAccountId)->sum('debit_minor');
        expect($debit)->toBeGreaterThan(0);
    });
});
