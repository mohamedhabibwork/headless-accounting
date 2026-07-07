<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Fulfillment\AllocationEngine;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;

beforeEach(function () {
    $this->order = (new CreateOrder)->execute(currency: 'EUR');
});

describe('AllocationEngine::allocateByWarehousePriority', function () {
    it('allocates a line to the highest-priority warehouse that has stock', function () {
        $whA = Warehouse::factory()->create(['code' => 'WH-A', 'priority' => 100]);
        $whB = Warehouse::factory()->create(['code' => 'WH-B', 'priority' => 50]);
        $whC = Warehouse::factory()->create(['code' => 'WH-C', 'priority' => 200]);

        $whA->update(['location_id' => Location::create(['code' => 'WH-A-LOC', 'name' => 'A'])->id]);
        $whB->update(['location_id' => Location::create(['code' => 'WH-B-LOC', 'name' => 'B'])->id]);
        $whC->update(['location_id' => Location::create(['code' => 'WH-C-LOC', 'name' => 'C'])->id]);

        $variant = ProductVariant::factory()->create();

        // Only WH-A has stock.
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $whA->location_id,
            'on_hand' => 5,
        ]);

        $engine = app(AllocationEngine::class);
        $allocations = $engine->allocateByWarehousePriority([
            ['variant_id' => $variant->id, 'quantity' => 3, 'weight_grams' => 100],
        ]);

        expect($allocations)->toHaveCount(1);
        expect((int) $allocations[0]['warehouse_id'])->toBe($whA->id);
        expect((int) $allocations[0]['quantity'])->toBe(3);
    });

    it('splits a line across warehouses when no single warehouse can fulfill it', function () {
        $whA = Warehouse::factory()->create(['code' => 'WH-A', 'priority' => 100]);
        $whB = Warehouse::factory()->create(['code' => 'WH-B', 'priority' => 50]);
        $whA->update(['location_id' => Location::create(['code' => 'WH-A-LOC', 'name' => 'A'])->id]);
        $whB->update(['location_id' => Location::create(['code' => 'WH-B-LOC', 'name' => 'B'])->id]);

        $variant = ProductVariant::factory()->create();
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $whA->location_id,
            'on_hand' => 3,
        ]);
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $whB->location_id,
            'on_hand' => 4,
        ]);

        $allocations = app(AllocationEngine::class)->allocateByWarehousePriority([
            ['variant_id' => $variant->id, 'quantity' => 5, 'weight_grams' => 50],
        ]);

        expect($allocations)->toHaveCount(2);
        // Priority ascending: WH-B (50) first, then WH-A (100).
        $byWh = collect($allocations)->keyBy('warehouse_id')->all();
        expect((int) $byWh[$whB->id]['quantity'])->toBe(4);
        expect((int) $byWh[$whA->id]['quantity'])->toBe(1);
    });

    it('throws when no warehouse has enough stock', function () {
        $whA = Warehouse::factory()->create(['code' => 'WH-A', 'priority' => 100]);
        $whA->update(['location_id' => Location::create(['code' => 'WH-A-LOC', 'name' => 'A'])->id]);

        $variant = ProductVariant::factory()->create();
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $whA->location_id,
            'on_hand' => 1,
        ]);

        expect(fn () => app(AllocationEngine::class)->allocateByWarehousePriority([
            ['variant_id' => $variant->id, 'quantity' => 100, 'weight_grams' => 0],
        ]))->toThrow(AccountingException::class);
    });
});

describe('AllocationEngine::allocateByProximity', function () {
    it('sorts warehouses by distance to ship-to coordinates', function () {
        $order = $this->order;
        $order->update(['shipping_address_snapshot' => [
            'latitude' => 48.8566, 'longitude' => 2.3522, // Paris
        ]]);

        $whNear = Warehouse::factory()->at('Paris', 'FR', 48.86, 2.34)->create(['code' => 'NEAR']);
        $whFar = Warehouse::factory()->at('Berlin', 'DE', 52.52, 13.40)->create(['code' => 'FAR']);

        $whNear->update(['location_id' => Location::create(['code' => 'NEAR-LOC', 'name' => 'Near'])->id]);
        $whFar->update(['location_id' => Location::create(['code' => 'FAR-LOC', 'name' => 'Far'])->id]);

        $variant = ProductVariant::factory()->create();
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $whNear->location_id,
            'on_hand' => 2,
        ]);
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $whFar->location_id,
            'on_hand' => 5,
        ]);

        $allocations = app(AllocationEngine::class)->allocateByProximity($order, [
            ['variant_id' => $variant->id, 'quantity' => 4, 'weight_grams' => 0],
        ]);

        expect($allocations[0]['warehouse_id'])->toBe($whNear->id);
        expect((int) $allocations[0]['quantity'])->toBe(2);
        expect((int) $allocations[1]['quantity'])->toBe(2);
    });
});

describe('AllocationEngine::allocateManual', function () {
    it('pins every line to a single warehouse', function () {
        $wh = Warehouse::factory()->default()->create(['code' => 'DEFAULT']);
        $variant = ProductVariant::factory()->create();

        $allocations = app(AllocationEngine::class)->allocateManual($this->order, [
            ['variant_id' => $variant->id, 'quantity' => 7, 'weight_grams' => 0],
        ]);

        expect($allocations)->toHaveCount(1);
        expect((int) $allocations[0]['warehouse_id'])->toBe($wh->id);
        expect((int) $allocations[0]['quantity'])->toBe(7);
    });
});

describe('AllocationEngine strategy dispatch', function () {
    it('rejects unknown strategies', function () {
        $variant = ProductVariant::factory()->create();
        expect(fn () => app(AllocationEngine::class)->allocate(
            order: $this->order,
            lines: [['variant_id' => $variant->id, 'quantity' => 1, 'weight_grams' => 0]],
            strategy: 'made_up',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('routes through the cheapest strategy when requested', function () {
        // Two warehouses with same stock; allocation goes to the first
        // priority — proves the dispatch is wired.
        $wh = Warehouse::factory()->create(['code' => 'WH', 'priority' => 10]);
        $wh->update(['location_id' => Location::create(['code' => 'WH-LOC', 'name' => 'W'])->id]);
        $variant = ProductVariant::factory()->create();
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $wh->location_id,
            'on_hand' => 4,
        ]);

        $allocations = app(AllocationEngine::class)->allocate(
            $this->order,
            [['variant_id' => $variant->id, 'quantity' => 1, 'weight_grams' => 0]],
            FulfillmentPlan::STRATEGY_CHEAPEST,
        );

        expect($allocations)->toHaveCount(1);
        expect((int) $allocations[0]['warehouse_id'])->toBe($wh->id);
    });
});
