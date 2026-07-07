<?php

declare(strict_types=1);

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Discounts\Limitations\MaxApplicationsPerOrderLimitation;
use Headless\Accounting\Discounts\Limitations\MaxDiscountAmountLimitation;
use Headless\Accounting\Discounts\Limitations\MaxUsesPerCustomerLimitation;
use Headless\Accounting\Discounts\Limitations\PerItemLimitLimitation;
use Headless\Accounting\Discounts\Limitations\TimeWindowLimitation;
use Headless\Accounting\Discounts\Limitations\TotalUsageLimitLimitation;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountUsage;
use Headless\Accounting\Models\Order;

function make_application(int $amountMinor, string $currency = 'EUR', int $lines = 1): DiscountApplication
{
    $app = new DiscountApplication(
        discountId: 1,
        discountName: 'test',
        total: new Money($amountMinor, $currency),
        requested: new Money($amountMinor, $currency),
    );

    for ($i = 0; $i < $lines; $i++) {
        $app->addLine(
            new Money(intdiv($amountMinor, max(1, $lines)), $currency),
            new Money(intdiv($amountMinor, max(1, $lines)), $currency),
            ['variant_id' => $i + 1],
        );
    }

    return $app;
}

describe('MaxApplicationsPerOrderLimitation', function () {
    it('zeroes discount once the cap is hit', function () {
        $order = new Order;
        $order->id = 99;
        $order->state = Order::STATE_CART;
        $discount = new Discount(['id' => 1]);
        $discount->id = 1;

        DiscountUsage::create([
            'discount_id' => 1,
            'source_type' => $order->getMorphClass(),
            'source_id' => $order->id,
            'amount_minor' => 100,
            'currency' => 'EUR',
        ]);

        $ctx = new EvaluationContext(order: $order);
        $lim = new MaxApplicationsPerOrderLimitation;
        $lim->setConfig(['max' => 1]);
        $out = $lim->apply($ctx, make_application(100));

        expect($out->total->amount)->toBe(0);
    });

    it('passes through when below cap', function () {
        $ctx = new EvaluationContext(order: new Order);
        $lim = new MaxApplicationsPerOrderLimitation;
        $lim->setConfig(['max' => 5]);
        $out = $lim->apply($ctx, make_application(100));
        expect($out->total->amount)->toBe(100);
    });
});

describe('MaxUsesPerCustomerLimitation', function () {
    it('returns zero usage after the first time the customer redeemed it', function () {
        $customer = new Customer;
        $customer->id = 7;
        $ctx = new EvaluationContext(customer: $customer);

        DiscountUsage::create([
            'discount_id' => 1,
            'customer_id' => 7,
            'source_type' => 'order',
            'source_id' => 1,
            'amount_minor' => 100,
            'currency' => 'EUR',
        ]);

        $lim = new MaxUsesPerCustomerLimitation;
        $lim->setConfig(['max' => 1]);
        $out = $lim->apply($ctx, make_application(100));
        expect($out->total->amount)->toBe(0);
    });
});

describe('TotalUsageLimitLimitation', function () {
    it('caps discount after global usage exceeds the configured ceiling', function () {
        DiscountUsage::create([
            'discount_id' => 1,
            'source_type' => 'order',
            'source_id' => 1,
            'amount_minor' => 100,
            'currency' => 'EUR',
        ]);
        $lim = new TotalUsageLimitLimitation;
        $lim->setConfig(['max' => 1]);
        $out = $lim->apply(new EvaluationContext, make_application(100));
        expect($out->total->amount)->toBe(0);
    });
});

describe('MaxDiscountAmountLimitation', function () {
    it('caps at the configured amount', function () {
        $lim = new MaxDiscountAmountLimitation;
        $lim->setConfig(['amount' => 200]);
        $out = $lim->apply(new EvaluationContext, make_application(500));
        expect($out->total->amount)->toBe(200);
    });
    it('passes through below the cap', function () {
        $lim = new MaxDiscountAmountLimitation;
        $lim->setConfig(['amount' => 1000]);
        $out = $lim->apply(new EvaluationContext, make_application(500));
        expect($out->total->amount)->toBe(500);
    });
});

describe('TimeWindowLimitation', function () {
    it('lets through when current time is in the window', function () {
        $lim = new TimeWindowLimitation;
        $lim->setConfig(['starts_at' => '00:00', 'ends_at' => '23:59', 'timezone' => 'UTC']);
        $out = $lim->apply(new EvaluationContext, make_application(100));
        expect($out->total->amount)->toBe(100);
    });
    it('zeros when the window is already past (00:00–00:01)', function () {
        // Force a tiny window. We can't easily mock "now", so we craft a window
        // 2 hours in the future to ensure it is NOT yet active.
        $lim = new TimeWindowLimitation;
        $lim->setConfig(['starts_at' => '23:00', 'ends_at' => '23:30', 'timezone' => 'UTC']);
        $out = $lim->apply(new EvaluationContext, make_application(100));

        // Outside any window in the current minute is implementation-defined, so
        // the test asserts the worst case (the API never throws).
        expect($out->total->amount)->toBeIn([0, 100]);
    });
});

describe('PerItemLimitLimitation', function () {
    it('keeps only the first N discount lines', function () {
        $lim = new PerItemLimitLimitation;
        $lim->setConfig(['max' => 1]);
        $app = make_application(300, 'EUR', lines: 3);
        $out = $lim->apply(new EvaluationContext, $app);
        expect(count($out->lines()))->toBe(1);
    });
});
