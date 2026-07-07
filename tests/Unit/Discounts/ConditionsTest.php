<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Headless\Accounting\Discounts\Conditions\CategoryInCondition;
use Headless\Accounting\Discounts\Conditions\ChannelCondition;
use Headless\Accounting\Discounts\Conditions\CountryCondition;
use Headless\Accounting\Discounts\Conditions\CouponCodeCondition;
use Headless\Accounting\Discounts\Conditions\CustomerGroupCondition;
use Headless\Accounting\Discounts\Conditions\DateRangeCondition;
use Headless\Accounting\Discounts\Conditions\DayOfWeekCondition;
use Headless\Accounting\Discounts\Conditions\MinItemQuantityCondition;
use Headless\Accounting\Discounts\Conditions\MinOrderAmountCondition;
use Headless\Accounting\Discounts\Conditions\PaymentMethodCondition;
use Headless\Accounting\Discounts\Conditions\ProductInCondition;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\CustomerGroup;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

function cond_order_with_items(int $subtotalMinor, array $itemSpec = []): EvaluationContext
{
    $items = [];
    foreach ($itemSpec as $spec) {
        $variant = new ProductVariant;
        $variant->id = $spec['variant_id'] ?? 1;
        $variant->product_id = $spec['product_id'] ?? 1;
        $i = new OrderItem;
        $i->variant_id = $spec['variant_id'] ?? 1;
        $i->unit_price_minor = $spec['unit_price'] ?? $subtotalMinor;
        $i->currency = 'EUR';
        $i->quantity = $spec['quantity'] ?? 1;
        $i->setRelation('variant', $variant);
        $items[] = $i;
    }
    if (! $items) {
        $items[] = new OrderItem(['unit_price_minor' => $subtotalMinor, 'currency' => 'EUR', 'quantity' => 1, 'variant_id' => 1]);
    }

    return new EvaluationContext(items: $items);
}

describe('MinOrderAmountCondition', function () {
    it('passes when subtotal >= threshold', function () {
        $c = new MinOrderAmountCondition;
        $c->setConfig(['amount' => 1000, 'currency' => 'EUR']);
        expect($c->passes(cond_order_with_items(1500)))->toBeTrue();
    });
    it('fails below threshold', function () {
        $c = new MinOrderAmountCondition;
        $c->setConfig(['amount' => 2000, 'currency' => 'EUR']);
        expect($c->passes(cond_order_with_items(1500)))->toBeFalse();
    });
});

describe('MinItemQuantityCondition', function () {
    it('passes when product count meets the threshold', function () {
        $c = new MinItemQuantityCondition;
        $c->setConfig(['products' => [1], 'quantity' => 3]);
        expect($c->passes(cond_order_with_items(0, [['product_id' => 1, 'quantity' => 4]])))->toBeTrue();
    });
    it('fails when below', function () {
        $c = new MinItemQuantityCondition;
        $c->setConfig(['products' => [1], 'quantity' => 10]);
        expect($c->passes(cond_order_with_items(0, [['product_id' => 1, 'quantity' => 4]])))->toBeFalse();
    });
});

describe('ProductInCondition', function () {
    it('passes when any ordered product is in the list', function () {
        $c = new ProductInCondition;
        $c->setConfig(['products' => [2]]);
        expect($c->passes(cond_order_with_items(0, [['product_id' => 2, 'quantity' => 1]])))->toBeTrue();
    });
    it('fails when no overlap', function () {
        $c = new ProductInCondition;
        $c->setConfig(['products' => [99]]);
        expect($c->passes(cond_order_with_items(0, [['product_id' => 2, 'quantity' => 1]])))->toBeFalse();
    });
    it('passes when no products specified (vacuous truth)', function () {
        $c = new ProductInCondition;
        expect($c->passes(cond_order_with_items(0, [['product_id' => 1, 'quantity' => 1]])))->toBeTrue();
    });
});

describe('CategoryInCondition', function () {
    it('passes when an ordered product belongs to a listed category', function () {
        $c = new CategoryInCondition;
        $c->setConfig(['categories' => [10]]);
        $ctx = cond_order_with_items(0, []);
        // Inject a faux category mapping on the variant's product.
        $items = iterator_to_array($ctx->items);
        $category = new class
        {
            public int $id = 10;
        };
        $product = new class($category)
        {
            public array $categories;

            public function __construct($c)
            {
                $this->categories = [$c];
            }
        };
        $variant = new class($product)
        {
            public object $product;

            public function __construct($p)
            {
                $this->product = $p;
            }
        };
        $items[0]->setRelation('variant', $variant);
        $ctx = new EvaluationContext(items: $items);
        expect($c->passes($ctx))->toBeTrue();
    });
});

describe('CustomerGroupCondition', function () {
    it('passes when customer group matches', function () {
        $group = new CustomerGroup(['code' => 'vip']);
        $customer = new Customer;
        $customer->id = 1;
        $customer->setRelation('groups', new Collection([$group]));

        $ctx = new EvaluationContext(customer: $customer);
        $c = new CustomerGroupCondition;
        $c->setConfig(['groups' => ['vip']]);
        expect($c->passes($ctx))->toBeTrue();
    });
    it('fails when customer not in any of the groups', function () {
        $customer = new Customer;
        $customer->id = 1;
        $customer->setRelation('groups', new Collection([]));

        $c = new CustomerGroupCondition;
        $c->setConfig(['groups' => ['vip']]);
        expect($c->passes(new EvaluationContext(customer: $customer)))->toBeFalse();
    });
    it('passes when no groups required', function () {
        $c = new CustomerGroupCondition;
        expect($c->passes(new EvaluationContext))->toBeTrue();
    });
});

describe('ChannelCondition', function () {
    it('passes when order channel matches', function () {
        $order = new Order(['channel_code' => 'web']);
        $c = new ChannelCondition;
        $c->setConfig(['channels' => ['web']]);
        expect($c->passes(new EvaluationContext(order: $order)))->toBeTrue();
    });
});

describe('DateRangeCondition', function () {
    it('passes when within range', function () {
        $c = new DateRangeCondition;
        $c->setConfig([
            'starts_at' => CarbonImmutable::now()->subDay()->toDateString(),
            'ends_at' => CarbonImmutable::now()->addDay()->toDateString(),
        ]);
        expect($c->passes(new EvaluationContext))->toBeTrue();
    });
    it('fails before range', function () {
        $c = new DateRangeCondition;
        $c->setConfig([
            'starts_at' => CarbonImmutable::now()->addDay()->toDateString(),
        ]);
        expect($c->passes(new EvaluationContext))->toBeFalse();
    });
});

describe('DayOfWeekCondition', function () {
    it('passes with today', function () {
        $c = new DayOfWeekCondition;
        $c->setConfig(['days' => [strtolower(CarbonImmutable::now()->format('D'))]]);
        expect($c->passes(new EvaluationContext))->toBeTrue();
    });
    it('fails with mismatched days', function () {
        $c = new DayOfWeekCondition;
        $c->setConfig(['days' => ['none-of-this']]);
        expect($c->passes(new EvaluationContext))->toBeFalse();
    });
});

describe('CouponCodeCondition', function () {
    it('matches case-insensitively', function () {
        $c = new CouponCodeCondition;
        $c->setConfig(['codes' => ['BF2026']]);
        expect($c->passes(new EvaluationContext(extras: ['coupon' => 'bf2026'])))->toBeTrue();
        expect($c->passes(new EvaluationContext(extras: ['coupon' => 'wrong'])))->toBeFalse();
    });
    it('passes vacuously when no code required', function () {
        $c = new CouponCodeCondition;
        expect($c->passes(new EvaluationContext))->toBeTrue();
    });
});

describe('CountryCondition', function () {
    it('matches shipping country', function () {
        $addr = new class
        {
            public string $country_code = 'FR';
        };
        $c = new CountryCondition;
        $c->setConfig(['countries' => ['FR']]);
        expect($c->passes(new EvaluationContext(shippingAddress: $addr)))->toBeTrue();
    });
    it('fails on mismatch', function () {
        $addr = new class
        {
            public string $country_code = 'US';
        };
        $c = new CountryCondition;
        $c->setConfig(['countries' => ['FR']]);
        expect($c->passes(new EvaluationContext(shippingAddress: $addr)))->toBeFalse();
    });
});

describe('PaymentMethodCondition', function () {
    it('passes when payment method matches', function () {
        $c = new PaymentMethodCondition;
        $c->setConfig(['methods' => ['card', 'sepa']]);
        expect($c->passes(new EvaluationContext(extras: ['payment_method' => 'sepa'])))->toBeTrue();
        expect($c->passes(new EvaluationContext(extras: ['payment_method' => 'crypto'])))->toBeFalse();
    });
});
