<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Order\AddItemToOrder;
use Headless\Accounting\Actions\Order\CalculateOrderTotals;
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Discounts\Drivers\PercentageDiscount;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\Price;
use Headless\Accounting\Models\PriceList;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Pricing\PricingResolver;

beforeEach(function () {
    $this->installChart();
});

it('applies a different price per channel and stacks with a channel-scoped discount', function () {
    $variant = ProductVariant::factory()->create();
    $web = $this->makeChannel('web', 'EUR');
    $pos = $this->makeChannel('pos', 'USD');

    // Web EU price list: 1299
    PriceList::create([
        'name' => 'Web EU', 'code' => 'web-'.uniqid(),
        'scope' => 'channel', 'scope_ref' => 'web',
        'currency' => 'EUR', 'priority' => 100, 'active' => true,
    ])->prices()->create([
        'subject_type' => $variant->getMorphClass(), 'subject_id' => $variant->id,
        'amount_minor' => 1299, 'currency' => 'EUR', 'min_quantity' => 1,
    ]);

    // POS US price list: 1499
    PriceList::create([
        'name' => 'POS US', 'code' => 'pos-'.uniqid(),
        'scope' => 'channel', 'scope_ref' => 'pos',
        'currency' => 'USD', 'priority' => 100, 'active' => true,
    ])->prices()->create([
        'subject_type' => $variant->getMorphClass(), 'subject_id' => $variant->id,
        'amount_minor' => 1499, 'currency' => 'USD', 'min_quantity' => 1,
    ]);

    $priceWeb = app(PricingResolver::class)->resolve(variant: $variant, currency: 'EUR', channel: $web);
    $pricePos = app(PricingResolver::class)->resolve(variant: $variant, currency: 'USD', channel: $pos);

    expect($priceWeb->amount->amount)->toBe(1299);
    expect($pricePos->amount->amount)->toBe(1499);

    // Apply a web-only discount (5 % off), and verify total on a EUR web order.
    Discount::create([
        'name' => 'Web only 5%', 'type' => PercentageDiscount::class,
        'active' => true, 'stackable' => true, 'priority' => 100,
        'channel_code' => 'web',                       // scoped to web
        'config' => ['percent' => 5],
    ]);

    $order = (new CreateOrder)->execute(currency: 'EUR', channel: 'web');
    app(AddItemToOrder::class)->execute(order: $order, variant: $variant, quantity: 1, unitPriceMinorOverride: 1299);
    $totals = app(CalculateOrderTotals::class)->execute($order);

    expect($totals->discount_total_minor)->toBe(65);      // 5% of 1299 = 64.95 → 65
});

it('renders prices with locale-aware separators per channel', function () {
    $variant = ProductVariant::factory()->create();
    PriceList::create([
        'name' => 'global', 'code' => 'g-'.uniqid(),
        'scope' => 'global', 'currency' => 'EUR',
        'priority' => 100, 'active' => true,
    ])->prices()->create([
        'subject_type' => $variant->getMorphClass(), 'subject_id' => $variant->id,
        'amount_minor' => 1299, 'currency' => 'EUR',
    ]);

    $en = app(PricingResolver::class)->resolve(variant: $variant, currency: 'EUR', locale: 'en');
    $fr = app(PricingResolver::class)->resolve(variant: $variant, currency: 'EUR', locale: 'fr');

    expect($en->localized('en'))->toContain('€');
    expect($fr->localized('fr'))->toContain(',');
    expect($fr->localized('fr'))->toContain("\u{202F}");
});
