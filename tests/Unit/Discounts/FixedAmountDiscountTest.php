<?php

declare(strict_types=1);

use Headless\Accounting\Discounts\Drivers\FixedAmountDiscount;
use Headless\Accounting\Discounts\EvaluationContext;

describe('FixedAmountDiscount', function () {

    it('applies a flat reduction', function () {
        $ctx = new EvaluationContext(items: pct_items([1000, 1000]));
        $d = new FixedAmountDiscount;
        $d->setConfig(['amount' => 500, 'currency' => 'EUR']);

        expect($d->calculate($ctx, $ctx->items)->total->amount)->toBe(500);
    });

    it('caps at the line subtotal', function () {
        $ctx = new EvaluationContext(items: pct_items([200]));
        $d = new FixedAmountDiscount;
        $d->setConfig(['amount' => 5000, 'currency' => 'EUR']);

        expect($d->calculate($ctx, $ctx->items)->total->amount)->toBe(200);
    });

    it('rejects zero or negative amounts', function () {
        $d = new FixedAmountDiscount;

        $d->setConfig(['amount' => 0]);
        expect(fn () => $d->calculate(new EvaluationContext, []))->toThrow(InvalidArgumentException::class);

        $d->setConfig(['amount' => -10]);
        expect(fn () => $d->calculate(new EvaluationContext, []))->toThrow(InvalidArgumentException::class);
    });

    it('distributes across multiple lines using largest-remainder-first', function () {
        $ctx = new EvaluationContext(items: pct_items([300, 300, 300]));   // 900 total
        $d = new FixedAmountDiscount;
        $d->setConfig(['amount' => 200, 'currency' => 'EUR', 'distribute_per_line' => true]);

        $app = $d->calculate($ctx, $ctx->items);
        $sum = array_sum(array_map(fn ($l) => $l['amount']->amount, $app->lines()));
        expect($sum)->toBe(200);
    });

    it('records a clipped-by note when exceeded subtotal', function () {
        $ctx = new EvaluationContext(items: pct_items([100]));
        $d = new FixedAmountDiscount;
        $d->setConfig(['amount' => 1000, 'currency' => 'EUR']);

        $app = $d->calculate($ctx, $ctx->items);
        $line = $app->lines()[0];
        expect($line['clipped_by'])->toBe('exceeds_subtotal');
    });
});
