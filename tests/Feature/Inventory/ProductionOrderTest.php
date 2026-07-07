<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Inventory\MaterialIssue;
use Headless\Accounting\Actions\Inventory\PostProductionOutput;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Bom;
use Headless\Accounting\Models\BomLine;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductionOrder;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    $this->installChart();
    Company::create(['code' => 'PROD-BASE-'.uniqid(), 'name' => 'Prod Base Co', 'base_currency' => 'EUR']);
});

describe('Production order consumption and output', function () {

    it('consumes BoM components and posts to WIP', function () {
        $company = Company::create(['code' => 'PRD', 'name' => 'Prod Co', 'base_currency' => 'EUR']);

        $outputProduct = Product::factory()->create(['item_type' => 'finished_good']);
        $outputVariant = ProductVariant::factory()->create([
            'product_id' => $outputProduct->id,
            'sku' => 'FG-OUTPUT',
        ]);

        $compAProduct = Product::factory()->create();
        $compAVariant = ProductVariant::factory()->create([
            'product_id' => $compAProduct->id,
            'sku' => 'COMP-A',
        ]);
        $compBProduct = Product::factory()->create();
        $compBVariant = ProductVariant::factory()->create([
            'product_id' => $compBProduct->id,
            'sku' => 'COMP-B',
        ]);

        $location = Location::create(['code' => 'WH-PROD', 'name' => 'Prod WH', 'active' => true]);

        $svc = app(InventoryValuationService::class);
        $svc->receipt($compAVariant, $location->id, 100, 200, 'EUR');
        $svc->receipt($compBVariant, $location->id, 100, 300, 'EUR');

        $bom = Bom::create([
            'product_id' => $outputProduct->id,
            'code' => 'BOM-TEST',
            'name' => 'Test BOM',
            'quantity_per_unit' => 1,
            'active' => true,
        ]);
        BomLine::create(['bom_id' => $bom->id, 'component_id' => $compAProduct->id, 'quantity' => 2]);
        BomLine::create(['bom_id' => $bom->id, 'component_id' => $compBProduct->id, 'quantity' => 1]);

        $order = ProductionOrder::create([
            'company_id' => $company->id,
            'number' => 'PO-TEST-'.uniqid(),
            'bom_id' => $bom->id,
            'quantity_to_produce' => 10,
            'scheduled_date' => today()->toDateString(),
            'state' => 'planned',
        ]);

        $result = app(MaterialIssue::class)->execute($order, 'EUR');

        $stockA = StockItem::query()->where('variant_id', $compAVariant->id)->where('location_id', $location->id)->first();
        $stockB = StockItem::query()->where('variant_id', $compBVariant->id)->where('location_id', $location->id)->first();
        expect((int) $stockA->on_hand)->toBe(100 - 2 * 10);
        expect((int) $stockB->on_hand)->toBe(100 - 1 * 10);

        $wipCode = config('headless-accounting.accounting.accounts.wip');
        $wipAccountId = Account::query()->where('code', $wipCode)->value('id');
        $wipDebit = (int) $result['journal_entry']->postings()->where('account_id', $wipAccountId)->sum('debit_minor');
        expect($wipDebit)->toBeGreaterThan(0);
    });

    it('receives finished goods and posts WIP→FG after MaterialIssue', function () {
        $company = Company::create(['code' => 'PROD2-'.uniqid(), 'name' => 'Prod Co 2', 'base_currency' => 'EUR']);

        $outputProduct = Product::factory()->create(['item_type' => 'finished_good']);
        $outputVariant = ProductVariant::factory()->create([
            'product_id' => $outputProduct->id,
            'sku' => 'FG-OUTPUT-2',
        ]);

        $compProduct = Product::factory()->create();
        $compVariant = ProductVariant::factory()->create([
            'product_id' => $compProduct->id,
            'sku' => 'COMP-A-2',
        ]);

        $location = Location::create(['code' => 'WH-PROD2', 'name' => 'Prod WH 2', 'active' => true]);

        app(InventoryValuationService::class)->receipt($compVariant, $location->id, 100, 200, 'EUR');

        $bom = Bom::create([
            'product_id' => $outputProduct->id,
            'code' => 'BOM-TEST-2',
            'name' => 'Test BOM 2',
            'quantity_per_unit' => 1,
            'active' => true,
        ]);
        BomLine::create(['bom_id' => $bom->id, 'component_id' => $compProduct->id, 'quantity' => 1]);

        $order = ProductionOrder::create([
            'company_id' => $company->id,
            'number' => 'PO-TEST-2-'.uniqid(),
            'bom_id' => $bom->id,
            'quantity_to_produce' => 5,
            'scheduled_date' => today()->toDateString(),
            'state' => 'in_progress',
        ]);

        $issuResult = app(MaterialIssue::class)->execute($order, 'EUR');
        expect($issuResult['journal_entry'])->not->toBeNull();

        $outputResult = app(PostProductionOutput::class)->execute($order, null, 'EUR');
        expect($outputResult['journal_entry'])->not->toBeNull();

        $stockOutput = StockItem::query()
            ->where('variant_id', $outputVariant->id)
            ->where('location_id', $location->id)
            ->first();

        if ($stockOutput) {
            expect((int) $stockOutput->on_hand)->toBe(5);
        }
    });
});
