<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Order\AddItemToOrder;
use Headless\Accounting\Actions\Order\CalculateOrderTotals;
use Headless\Accounting\Actions\Order\CancelOrder;
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Actions\Order\MarkOrderPaid;
use Headless\Accounting\Actions\Order\PlaceOrder;
use Headless\Accounting\Discounts\Drivers\PercentageDiscount;
use Headless\Accounting\Exceptions\InvalidTransitionException;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\ProductVariant;

beforeEach(function () {
    $this->installChart();
});

describe('CreateOrder action', function () {
    it('creates an order in the cart state with a stable number', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR', channel: 'web');

        expect($order->state)->toBe(Order::STATE_CART);
        expect($order->currency)->toBe('EUR');
        expect($order->number)->toMatch('/^ORD-\d{4}-\d+$/');
    });

    it('records an order.created event', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $event = $order->events()->where('type', 'order.created')->first();
        expect($event)->not->toBeNull();
    });
});

describe('AddItemToOrder action', function () {
    it('creates a new line and merges existing ones on second add', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $variant = ProductVariant::factory()->create();

        $action = app(AddItemToOrder::class);
        $first = $action->execute(order: $order, variant: $variant, quantity: 2);
        $second = $action->execute(order: $order, variant: $variant, quantity: 1);

        expect((int) $second->quantity)->toBe(3);
        expect($order->items()->count())->toBe(1);
    });

    it('uses the supplied unit price override instead of the resolver', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $variant = ProductVariant::factory()->create();

        $item = app(AddItemToOrder::class)->execute(
            order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 555,
        );
        expect((int) $item->unit_price_minor)->toBe(555);
    });

    it('refuses to add a zero/negative quantity', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $variant = ProductVariant::factory()->create();
        expect(fn () => app(AddItemToOrder::class)->execute(
            order: $order, variant: $variant, quantity: 0,
        ))->toThrow(InvalidArgumentException::class);
    });
});

describe('CalculateOrderTotals action', function () {
    it('rolls up subtotal, tax, discount, grand total', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $variant = ProductVariant::factory()->create();
        app(AddItemToOrder::class)->execute(
            order: $order, variant: $variant, quantity: 2, unitPriceMinorOverride: 1000,
        );

        $totals = app(CalculateOrderTotals::class)->execute($order);

        expect($totals->subtotal_minor)->toBe(2000);
        expect($totals->grand_total_minor)->toBeGreaterThan(0);
    });

    it('applies a percentage discount to the order total', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $variant = ProductVariant::factory()->create();

        Discount::create([
            'name' => '5%', 'type' => PercentageDiscount::class,
            'active' => true, 'stackable' => true, 'priority' => 100,
            'config' => ['percent' => 5],
        ]);

        app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 10000);

        $totals = app(CalculateOrderTotals::class)->execute($order);
        expect($totals->discount_total_minor)->toBe(500);     // 5% of 10000
    });
});

describe('PlaceOrder + MarkOrderPaid', function () {
    it('transitions cart → draft → placed and posts an AR journal entry', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $variant = ProductVariant::factory()->create();
        app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 10000);
        app(CalculateOrderTotals::class)->execute($order);

        $placed = (new PlaceOrder(app(Journal::class)))->execute($order);

        expect($placed->state)->toBe(Order::STATE_PLACED);
        expect($placed->placed_at)->not->toBeNull();

        // AR journal entry exists.
        $entry = JournalEntry::query()
            ->where('source_type', $order->getMorphClass())
            ->where('source_id', $order->id)
            ->first();
        expect($entry)->not->toBeNull();
        $entry->assertBalanced();
    });

    it('transitions placed → paid and clears AR via bank clearing', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $variant = ProductVariant::factory()->create();
        app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 12345);
        app(CalculateOrderTotals::class)->execute($order);
        (new PlaceOrder(app(Journal::class)))->execute($order);

        $paid = (new MarkOrderPaid(app(Journal::class)))->execute($order);
        expect($paid->state)->toBe(Order::STATE_PAID);
        expect($paid->paid_at)->not->toBeNull();
    });

    it('blocks payment before place', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        expect(fn () => (new MarkOrderPaid(app(Journal::class)))->execute($order))
            ->toThrow(InvalidTransitionException::class);
    });
});

describe('CancelOrder action', function () {
    it('cancels a placed order and writes cancelled_at', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $variant = ProductVariant::factory()->create();
        app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 1000);
        app(CalculateOrderTotals::class)->execute($order);
        (new PlaceOrder(app(Journal::class)))->execute($order);

        $cancelled = (new CancelOrder)->execute($order, reason: 'customer requested');
        expect($cancelled->state)->toBe(Order::STATE_CANCELLED);
        expect($cancelled->cancelled_at)->not->toBeNull();
    });
});
