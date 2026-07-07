<?php

declare(strict_types=1);

use Headless\Accounting\Inventory\CostMethods;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    $this->installChart();
    Company::create(['code' => 'INV', 'name' => 'Inv Co', 'base_currency' => 'EUR']);
});

describe('Inventory valuation', function () {

    it('FIFO consumes oldest layer first', function () {
        $location = Location::create(['code' => 'WH-FIFO', 'name' => 'FIFO WH', 'active' => true]);
        $variant = ProductVariant::factory()->create();

        $svc = app(InventoryValuationService::class);
        $svc->receipt($variant, $location->id, 5, 1000, 'EUR', CostMethods::METHOD_FIFO);
        usleep(1_100_000);
        $svc->receipt($variant, $location->id, 5, 1200, 'EUR', CostMethods::METHOD_FIFO);

        $consumed = $svc->issue($variant, $location->id, 6, CostMethods::METHOD_FIFO);

        $totalCost = 0;
        foreach ($consumed as $row) {
            $totalCost += ((int) $row['quantity']) * ((int) $row['unit_cost_minor']);
        }
        expect($totalCost)->toBe(5 * 1000 + 1 * 1200);

        $remaining = (int) CostLayer::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $location->id)
            ->sum('quantity_remaining');
        expect($remaining)->toBe(4);
    });

    it('weighted average rolls a new average', function () {
        $location = Location::create(['code' => 'WH-WA', 'name' => 'WA WH', 'active' => true]);
        $variant = ProductVariant::factory()->create();

        $svc = app(InventoryValuationService::class);
        $svc->receipt($variant, $location->id, 5, 1000, 'EUR', CostMethods::METHOD_WEIGHTED);
        $svc->receipt($variant, $location->id, 5, 1500, 'EUR', CostMethods::METHOD_WEIGHTED);

        $consumed = $svc->issue($variant, $location->id, 3, CostMethods::METHOD_WEIGHTED);

        $totalCost = 0;
        foreach ($consumed as $row) {
            $totalCost += ((int) $row['quantity']) * ((int) $row['unit_cost_minor']);
        }
        $expected = (int) round(3 * ((5 * 1000 + 5 * 1500) / 10));
        expect($totalCost)->toBe($expected);
    });
});
