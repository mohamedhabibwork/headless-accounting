<?php

declare(strict_types=1);

use Headless\Accounting\Discounts\Drivers\BuyXGetYDiscount;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Models\ProductVariant;

function bxgy_item(int $price, ?int $productId = null, int $variantId = 1): OrderItem
{
    $variant = new ProductVariant(['product_id' => $productId ?? 1]);
    $variant->id = $variantId;
    $item = new OrderItem;
    $item->variant_id = $variantId;
    $item->unit_price_minor = $price;
    $item->currency = 'EUR';
    $item->quantity = 1;
    $item->setRelation('variant', $variant);

    return $item;
}

describe('BuyXGetYDiscount', function () {

    it('buy 2 get 1 free — picks the cheapest of each cycle', function () {
        $items = [
            bxgy_item(1000, 1),
            bxgy_item(1500, 1),
            bxgy_item(2000, 1),
        ];
        $d = new BuyXGetYDiscount;
        $d->setConfig([
            'buy_qty' => 2,
            'get_qty' => 1,
            'get_discount_percent' => 100,
            'buy_products' => [1],
            'get_products' => [1],
        ]);

        $app = $d->calculate(new EvaluationContext(items: $items), $items);

        // 3 units, 1 full cycle, cheapest = 1000 → free
        expect($app->total->amount)->toBe(1000);
    });

    it('returns empty when buy units below minimum cycle', function () {
        $items = [bxgy_item(1000, 1)];
        $d = new BuyXGetYDiscount;
        $d->setConfig(['buy_qty' => 2, 'get_qty' => 1, 'get_discount_percent' => 100]);

        expect($d->calculate(new EvaluationContext(items: $items), $items)->isEmpty())->toBeTrue();
    });

    it('respects multiple cycles (3 cycles = 6 buy + 3 free)', function () {
        $items = array_fill(0, 9, bxgy_item(1000, 1));   // 9 units
        $d = new BuyXGetYDiscount;
        $d->setConfig([
            'buy_qty' => 2, 'get_qty' => 1,
            'get_discount_percent' => 100,
            'buy_products' => [1], 'get_products' => [1],
        ]);

        $app = $d->calculate(new EvaluationContext(items: $items), $items);

        // 9 / (2+1) = 3 cycles
        expect($app->total->amount)->toBe(3000);
    });

    it('honors max_applications cap', function () {
        $items = array_fill(0, 9, bxgy_item(1000, 1));
        $d = new BuyXGetYDiscount;
        $d->setConfig([
            'buy_qty' => 2, 'get_qty' => 1,
            'get_discount_percent' => 100,
            'max_applications' => 1,
        ]);

        $app = $d->calculate(new EvaluationContext(items: $items), $items);
        expect($app->total->amount)->toBe(1000);
    });

    it('applies partial discount when get_discount_percent is 50', function () {
        $items = array_fill(0, 3, bxgy_item(1000, 1));
        $d = new BuyXGetYDiscount;
        $d->setConfig([
            'buy_qty' => 2, 'get_qty' => 1,
            'get_discount_percent' => 50,
        ]);

        $app = $d->calculate(new EvaluationContext(items: $items), $items);
        expect($app->total->amount)->toBe(500);
    });

    it('picks most expensive when selection=most_expensive', function () {
        $items = [
            bxgy_item(500, 1),
            bxgy_item(1500, 1),
            bxgy_item(2000, 1),
        ];
        $d = new BuyXGetYDiscount;
        $d->setConfig([
            'buy_qty' => 2, 'get_qty' => 1,
            'get_discount_percent' => 100,
            'selection' => 'most_expensive',
        ]);

        $app = $d->calculate(new EvaluationContext(items: $items), $items);
        // cheapest-by-default otherwise 500; now 2000
        expect($app->total->amount)->toBe(2000);
    });

    it('returns empty array when no items supplied', function () {
        $d = new BuyXGetYDiscount;
        $d->setConfig(['buy_qty' => 2, 'get_qty' => 1]);
        expect($d->calculate(new EvaluationContext, [])->isEmpty())->toBeTrue();
    });
});
