<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Order\AddItemToOrder;
use Headless\Accounting\Actions\Order\CalculateOrderTotals;
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Actions\Order\MarkOrderPaid;
use Headless\Accounting\Actions\Order\PlaceOrder;
use Headless\Accounting\Actions\Payment\CapturePayment;
use Headless\Accounting\Actions\Payment\RefundPayment;
use Headless\Accounting\Discounts\Conditions\MinOrderAmountCondition;
use Headless\Accounting\Discounts\Drivers\PercentageDiscount;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountCondition;
use Headless\Accounting\Models\DiscountUsage;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderAdjustment;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\TaxClass;
use Headless\Accounting\Models\TaxRate;
use Headless\Accounting\Payments\Contracts\Driver;
use Headless\Accounting\Payments\Contracts\Gateway;
use Headless\Accounting\Payments\PaymentResponse;

beforeEach(function () {
    $this->installChart();
    // Set up a French VAT zone (20%) so tax is non-zero.
    $zone = $this->makeTaxZone('eu-vat', 'EU');
    $zone->members()->create(['country_code' => 'FR', 'operator' => 'or']);
    $class = $this->makeTaxClass();
    TaxRate::create([
        'zone_id' => $zone->id,
        'tax_class_id' => $class->id,
        'name' => 'VAT 20%',
        'percent' => 20.0,
        'active' => true,
    ]);
    // Attach our product variant to that tax class so tax applies.
});

it('runs a full cart-to-paid flow with a discount, tax, payment, refund, and event stream', function () {
    // 1. Catalog
    $variant = ProductVariant::factory()->create([
        'name' => 'T-Shirt', 'sku' => 'TS-RED-M',
    ]);

    // Give the variant a tax class.
    $variant->product->update(['tax_class_id' => TaxClass::query()->first()->id]);

    // 2. Discount
    $discount = Discount::create([
        'name' => 'Spring 10%', 'type' => PercentageDiscount::class,
        'active' => true, 'stackable' => true, 'priority' => 100,
        'config' => ['percent' => 10],
    ]);
    DiscountCondition::create([
        'discount_id' => $discount->id,
        'type' => MinOrderAmountCondition::class,
        'config' => ['amount' => 1000, 'currency' => 'EUR'],
    ]);

    // 3. Cart → Order
    $order = (new CreateOrder)->execute(
        customer: $this->makeCustomer(),
        channel: 'web', currency: 'EUR', locale: 'fr-FR',
        shippingAddress: [
            'line1' => '1 rue Foo', 'city' => 'Paris',
            'country_code' => 'FR', 'postal_code' => '75001',
        ],
    );

    // 4. Add 2 items at 1999 cents.
    app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 2, unitPriceMinorOverride: 1999);

    // 5. Compute totals → 2*1999 = 3998, 10% off = -399.8 → -400 → 3598 subtotal-after-discount
    $totals = app(CalculateOrderTotals::class)->execute($order);
    expect($totals->discount_total_minor)->toBe(400);         // banker rounding of 399.8

    // Tax: 20% of 3598 → 719.6 → 720
    expect($totals->tax_total_minor)->toBe(720);

    // Grand: 3598 + 720 = 4318
    expect($totals->grand_total_minor)->toBe(4318);

    // 6. Place → AR journal entry
    $placed = (new PlaceOrder(app(Journal::class)))->execute($order);
    expect($placed->state)->toBe(Order::STATE_PLACED);
    $entry = JournalEntry::query()->where('source_id', $order->id)->first();
    expect($entry)->not->toBeNull();
    $entry->assertBalanced();

    // 7. Capture via mocked payment driver
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capture')->andReturn(
        PaymentResponse::success('pi_full', 4318, 'EUR'),
    );
    $driver->shouldReceive('refund')->andReturn(
        PaymentResponse::success('re_partial', 500, 'EUR'),
    );
    app(Gateway::class)->register('mock', $driver);

    $payment = (new CapturePayment(app(Gateway::class)))->execute(
        payable: $placed, driver: 'mock', method: 'card', token: 'pm_xxx', amountMinor: 4318,
    );
    expect($payment->state)->toBe(Payment::STATE_CAPTURED);

    $paid = (new MarkOrderPaid(app(Journal::class)))->execute($placed);
    expect($paid->state)->toBe(Order::STATE_PAID);

    // 8. Partial refund
    $refund = (new RefundPayment(app(Gateway::class)))->execute(
        payment: $payment, amountMinor: 500, reason: 'goodwill',
    );
    expect($refund->amount_minor)->toBe(500);
    expect($payment->fresh()->state)->toBe(Payment::STATE_PARTIAL_REFUNDED);

    // 9. DiscountUsage row exists
    $usage = DiscountUsage::query()->where('discount_id', $discount->id)->first();
    expect($usage)->not->toBeNull();
    expect($usage->source_id)->toBe($order->id);

    // 10. OrderAdjustment with negative amount is on the order
    $adjustment = OrderAdjustment::query()->where('order_id', $order->id)->where('type', 'discount')->first();
    expect((int) $adjustment->amount_minor)->toBeLessThan(0);

    // 11. Event stream has the order events
    expect($order->events()->where('type', 'order.created')->exists())->toBeTrue();
    expect($order->events()->where('type', 'order.placed')->exists())->toBeTrue();
    expect($order->events()->where('type', 'order.paid')->exists())->toBeTrue();
});

it('persists a separate sales-revenue journal entry when payment lands after a partial capture', function () {
    $variant = ProductVariant::factory()->create();
    $variant->product->update(['tax_class_id' => TaxClass::query()->first()->id]);

    $order = (new CreateOrder)->execute(currency: 'EUR', channel: 'web');
    app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 10000);
    app(CalculateOrderTotals::class)->execute($order);
    (new PlaceOrder(app(Journal::class)))->execute($order);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capture')->andReturn(
        PaymentResponse::success('pi_x', 10000, 'EUR'),
    );
    app(Gateway::class)->register('mock', $driver);

    (new CapturePayment(app(Gateway::class)))->execute(
        payable: $order, driver: 'mock', token: 'pi_x', amountMinor: 10000,
    );

    // Two journal entries for this order: one for placed, one for paid.
    expect(JournalEntry::query()->where('source_id', $order->id)->count())->toBe(2);
});
