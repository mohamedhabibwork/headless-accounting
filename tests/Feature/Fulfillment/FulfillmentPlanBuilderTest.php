<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Fulfillment\FulfillmentPlanBuilder;
use Headless\Accounting\Models\Carrier;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\ShippingRateCard;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;

describe('FulfillmentPlanBuilder', function () {
    it('builds a plan with allocations and ranked shipping options', function () {
        $wh = Warehouse::factory()->create(['code' => 'WH-MAIN', 'priority' => 10]);
        $wh->update(['location_id' => Location::create(['code' => 'WH-MAIN-LOC', 'name' => 'Main'])->id]);

        $variant = ProductVariant::factory()->create();
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $wh->location_id,
            'on_hand' => 10,
        ]);

        $carrier = Carrier::factory()->create(['code' => 'dhl']);
        ShippingRateCard::factory()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $wh->id,
            'service_code' => 'economy',
            'base_cost_minor' => 500,
            'per_kg_cost_minor' => 0,
            'currency' => 'EUR',
            'destinations' => ['FR'],
        ]);
        ShippingRateCard::factory()->express()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $wh->id,
            'service_code' => 'express',
            'base_cost_minor' => 1500,
            'per_kg_cost_minor' => 0,
            'currency' => 'EUR',
            'destinations' => ['FR'],
        ]);

        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->update([
            'shipping_address_snapshot' => ['country' => 'FR', 'city' => 'Paris'],
        ]);

        $builder = app(FulfillmentPlanBuilder::class);
        $plan = $builder->build(
            $order,
            [['variant_id' => $variant->id, 'quantity' => 3, 'weight_grams' => 1000]],
            FulfillmentPlan::STRATEGY_CHEAPEST,
        );

        expect($plan)->toBeInstanceOf(FulfillmentPlan::class);
        expect($plan->state)->toBe(FulfillmentPlan::STATE_ALLOCATED);
        expect($plan->totalUnits())->toBe(3);

        $options = $plan->shipping_options;
        expect($options)->toHaveCount(2);
        expect($options[0]['selected'])->toBeTrue();
        expect($options[0]['service_code'])->toBe('economy');
    });

    it('does not include shipping options when no rate cards match the destination', function () {
        $wh = Warehouse::factory()->create(['code' => 'WH', 'priority' => 10]);
        $wh->update(['location_id' => Location::create(['code' => 'WH-LOC', 'name' => 'L'])->id]);
        $variant = ProductVariant::factory()->create();
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $wh->location_id,
            'on_hand' => 5,
        ]);

        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->update(['shipping_address_snapshot' => ['country' => 'FR']]);

        $plan = app(FulfillmentPlanBuilder::class)->build(
            $order,
            [['variant_id' => $variant->id, 'quantity' => 1, 'weight_grams' => 500]],
            FulfillmentPlan::STRATEGY_PRIORITY,
        );

        expect($plan->shipping_options)->toBe([]);
    });
});
