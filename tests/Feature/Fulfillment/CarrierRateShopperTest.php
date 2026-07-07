<?php

declare(strict_types=1);

use Headless\Accounting\Fulfillment\CarrierRateShopper;
use Headless\Accounting\Models\Carrier;
use Headless\Accounting\Models\ShippingRateCard;
use Headless\Accounting\Models\Warehouse;

describe('ShippingRateCard quoting', function () {
    it('quotes a rate for a destination and weight', function () {
        $carrier = Carrier::factory()->create(['code' => 'dhl']);
        $warehouse = Warehouse::factory()->create();
        $card = ShippingRateCard::factory()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $warehouse->id,
            'base_cost_minor' => 500,
            'per_kg_cost_minor' => 200,
            'currency' => 'EUR',
            'destinations' => ['FR', 'DE'],
        ]);

        $quote = $card->quote('FR', 2000.0);    // 2 kg
        expect($quote)->not->toBeNull();
        expect((int) $quote['cost_minor'])->toBe(500 + 200 * 2);     // 900
        expect($quote['currency'])->toBe('EUR');
        expect($quote['carrier_code'])->toBe('dhl');
    });

    it('applies a free-shipping threshold when the value clears it', function () {
        $carrier = Carrier::factory()->create();
        $card = ShippingRateCard::factory()->create([
            'carrier_id' => $carrier->id,
            'base_cost_minor' => 1000,
            'per_kg_cost_minor' => 0,
            'free_shipping_threshold_minor' => 5000,
        ]);

        $quote = $card->quote('FR', 500.0, 4999);
        expect((int) $quote['cost_minor'])->toBe(1000);

        $quote2 = $card->quote('FR', 500.0, 5000);
        expect((int) $quote2['cost_minor'])->toBe(0);
    });

    it('rejects quotes outside the destination list', function () {
        $card = ShippingRateCard::factory()->create(['destinations' => ['DE']]);
        expect($card->quote('US', 100.0))->toBeNull();
    });

    it('rejects quotes above the max weight', function () {
        $card = ShippingRateCard::factory()->create([
            'min_weight_grams' => 0,
            'max_weight_grams' => 1000,
        ]);
        expect($card->quote('FR', 1500.0))->toBeNull();
    });

    it('accepts quotes for destinations with a wildcard', function () {
        $card = ShippingRateCard::factory()->create(['destinations' => ['*']]);
        expect($card->quote('JP', 100.0))->not->toBeNull();
    });
});

describe('CarrierRateShopper', function () {
    it('ranks multiple rate cards by cost', function () {
        $carrier = Carrier::factory()->create(['code' => 'dhl']);
        $warehouse = Warehouse::factory()->create();

        $cheap = ShippingRateCard::factory()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $warehouse->id,
            'service_code' => 'economy',
            'base_cost_minor' => 200,
            'per_kg_cost_minor' => 0,
            'priority' => 50,
        ]);
        $express = ShippingRateCard::factory()->express()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $warehouse->id,
            'base_cost_minor' => 1500,
            'per_kg_cost_minor' => 500,
            'priority' => 100,
        ]);

        $shopper = app(CarrierRateShopper::class);
        $ranked = $shopper->shop($warehouse, 'FR', 1000.0, 0, CarrierRateShopper::RANK_BY_COST);

        expect($ranked)->toHaveCount(2);
        expect($ranked[0]['service_code'])->toBe('economy');
        expect((int) $ranked[0]['cost_minor'])->toBeLessThan((int) $ranked[1]['cost_minor']);
    });

    it('ranks by ETA when the strategy is fastest', function () {
        $carrier = Carrier::factory()->create(['code' => 'ups']);
        $warehouse = Warehouse::factory()->create();

        ShippingRateCard::factory()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $warehouse->id,
            'service_code' => 'slow',
            'base_cost_minor' => 100,
            'per_kg_cost_minor' => 0,
            'eta_days_from' => 5,
            'eta_days_to' => 7,
        ]);
        ShippingRateCard::factory()->express()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $warehouse->id,
            'service_code' => 'fast',
            'base_cost_minor' => 1500,
            'per_kg_cost_minor' => 0,
            'eta_days_from' => 1,
            'eta_days_to' => 2,
        ]);

        $shopper = app(CarrierRateShopper::class);
        $ranked = $shopper->shop($warehouse, 'FR', 1000.0, 0, CarrierRateShopper::RANK_BY_FASTEST);

        expect($ranked[0]['service_code'])->toBe('fast');
    });

    it('returns the cheapest quote', function () {
        $carrier = Carrier::factory()->create();
        $warehouse = Warehouse::factory()->create();

        ShippingRateCard::factory()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $warehouse->id,
            'service_code' => 'a',
            'base_cost_minor' => 100,
            'per_kg_cost_minor' => 200,
        ]);
        ShippingRateCard::factory()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $warehouse->id,
            'service_code' => 'b',
            'base_cost_minor' => 50,
            'per_kg_cost_minor' => 0,
        ]);

        $shopper = app(CarrierRateShopper::class);
        $cheapest = $shopper->cheapest($warehouse, 'FR', 1000.0);
        expect((int) $cheapest['cost_minor'])->toBe(50);
        expect($cheapest['service_code'])->toBe('b');
    });

    it('returns null when no card matches', function () {
        $warehouse = Warehouse::factory()->create();
        $shopper = app(CarrierRateShopper::class);
        expect($shopper->cheapest($warehouse, 'FR', 1000.0))->toBeNull();
    });
});
