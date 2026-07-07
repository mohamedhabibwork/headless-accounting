<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Order\AddItemToOrder;
use Headless\Accounting\Actions\Order\CalculateOrderTotals;
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Discounts\Drivers\PercentageDiscount;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\TaxRate;

beforeEach(function () {
    $this->installChart();
});

it('applies a per-region tax at order-totals time', function () {
    $zone = $this->makeTaxZone('eu-vat', 'EU');
    $zone->members()->create(['country_code' => 'FR', 'operator' => 'or']);
    $class = $this->makeTaxClass();
    TaxRate::create([
        'zone_id' => $zone->id, 'tax_class_id' => $class->id,
        'name' => 'VAT 20%', 'percent' => 20.0, 'active' => true,
    ]);

    $variant = ProductVariant::factory()->create();
    $variant->product->update(['tax_class_id' => $class->id]);

    $order = (new CreateOrder)->execute(currency: 'EUR', channel: 'web');
    app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 10000);

    // Ship to France → tax applies
    $order->update([
        'shipping_address_snapshot' => [
            'line1' => '1 rue Foo', 'city' => 'Paris',
            'country_code' => 'FR', 'postal_code' => '75001',
        ],
    ]);
    $totals = app(CalculateOrderTotals::class)->execute($order);
    expect($totals->tax_total_minor)->toBe(2000);

    // Re-totals to outside France → no tax
    $order->update(['shipping_address_snapshot' => [
        'line1' => '1 rue Foo', 'city' => 'Nowhere',
        'country_code' => 'XX',
    ]]);
    $totals2 = app(CalculateOrderTotals::class)->execute($order->fresh());
    expect($totals2->tax_total_minor)->toBe(0);
});

it('caps a percentage discount when amount exceeds maximum_discount_amount', function () {
    $zone = $this->makeTaxZone('eu-vat', 'EU');
    $zone->members()->create(['country_code' => 'FR', 'operator' => 'or']);
    $class = $this->makeTaxClass();
    TaxRate::create([
        'zone_id' => $zone->id, 'tax_class_id' => $class->id,
        'name' => 'VAT 20%', 'percent' => 20.0, 'active' => true,
    ]);

    $variant = ProductVariant::factory()->create();
    $variant->product->update(['tax_class_id' => $class->id]);

    Discount::create([
        'name' => 'Capped 50%', 'type' => PercentageDiscount::class,
        'active' => true, 'stackable' => true, 'priority' => 100,
        'config' => ['percent' => 50, 'maximum_discount_amount' => 1000],
    ]);

    $order = (new CreateOrder)->execute(currency: 'EUR', channel: 'web');
    app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 100000);

    $order->update(['shipping_address_snapshot' => [
        'line1' => 'X', 'city' => 'Paris', 'country_code' => 'FR',
    ]]);
    $totals = app(CalculateOrderTotals::class)->execute($order);

    expect($totals->discount_total_minor)->toBe(1000);     // capped
});
