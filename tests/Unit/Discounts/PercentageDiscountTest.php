<?php

declare(strict_types=1);

use Headless\Accounting\Discounts\Drivers\PercentageDiscount;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;

function pct_items(array $unitPricesMinor): array
{
    return array_map(
        fn ($p) => new OrderItem(['unit_price_minor' => $p, 'currency' => 'EUR', 'quantity' => 1, 'name' => 'x', 'sku' => 's']),
        $unitPricesMinor,
    );
}

describe('PercentageDiscount', function () {

    it('computes a basic percentage', function () {
        $ctx = new EvaluationContext(items: pct_items([500, 500]));
        $d = new PercentageDiscount;
        $d->setConfig(['percent' => 10, '__name' => '10% off']);

        $app = $d->calculate($ctx, $ctx->items);

        expect($app->total->amount)->toBe(100);
        expect($app->requested->amount)->toBe(100);
    });

    it('honors the maximum_discount_amount cap', function () {
        $ctx = new EvaluationContext(items: pct_items([1000, 1000]));
        $d = new PercentageDiscount;
        $d->setConfig(['percent' => 50, 'maximum_discount_amount' => 500, '__name' => 'capped']);

        $app = $d->calculate($ctx, $ctx->items);
        expect($app->total->amount)->toBe(500);
        expect($app->requested->amount)->toBe(1000);
    });

    it('rejects percentages outside (0, 100]', function () {
        $d = new PercentageDiscount;
        $d->setConfig(['percent' => 0]);
        expect(fn () => $d->calculate(new EvaluationContext, []))->toThrow(InvalidArgumentException::class);

        $d->setConfig(['percent' => -10]);
        expect(fn () => $d->calculate(new EvaluationContext, []))->toThrow(InvalidArgumentException::class);

        $d->setConfig(['percent' => 150]);
        expect(fn () => $d->calculate(new EvaluationContext, []))->toThrow(InvalidArgumentException::class);
    });

    it('distributes the discount proportionally across candidate lines', function () {
        $ctx = new EvaluationContext(items: pct_items([250, 750]));
        $d = new PercentageDiscount;
        $d->setConfig(['percent' => 10, 'distribute_per_line' => true]);

        $app = $d->calculate($ctx, $ctx->items);
        $sum = array_sum(array_map(fn ($l) => $l['amount']->amount, $app->lines()));
        expect($sum)->toBe(100);                       // 10% of 1000
    });

    it('skips lines that are free (zero subtotal)', function () {
        $ctx = new EvaluationContext(items: pct_items([0, 500]));
        $d = new PercentageDiscount;
        $d->setConfig(['percent' => 50]);

        $app = $d->calculate($ctx, $ctx->items);
        expect($app->total->amount)->toBe(250);
    });

    it('returns Money in the configured or order currency', function () {
        $ctx = new EvaluationContext(items: pct_items([999]));
        $d = new PercentageDiscount;
        $d->setConfig(['percent' => 10, 'currency' => 'EUR']);

        $app = $d->calculate($ctx, $ctx->items);
        expect($app->total->currency)->toBe('EUR');
    });
});
