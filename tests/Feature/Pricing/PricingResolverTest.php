<?php

declare(strict_types=1);

use Headless\Accounting\Models\PriceList;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Pricing\PricingResolver;

beforeEach(function () {
    $this->makeCurrency();
});

describe('PricingResolver', function () {

    it('returns zero price when no lists exist', function () {
        $variant = ProductVariant::factory()->create();
        $resolver = app(PricingResolver::class);

        $price = $resolver->resolve(variant: $variant, currency: 'EUR');

        expect($price->amount->amount)->toBe(0);
        expect($price->amount->currency)->toBe('EUR');
    });

    it('returns the global-list price when nothing channel-specific applies', function () {
        $variant = ProductVariant::factory()->create();
        PriceList::create([
            'name' => 'Global', 'code' => 'global-'.uniqid(),
            'scope' => 'global', 'currency' => 'EUR',
            'priority' => 100, 'active' => true,
        ])->prices()->create([
            'subject_type' => $variant->getMorphClass(),
            'subject_id' => $variant->id,
            'amount_minor' => 1999,
            'currency' => 'EUR',
        ]);

        $resolver = app(PricingResolver::class);
        $price = $resolver->resolve(variant: $variant, currency: 'EUR');

        expect($price->amount->amount)->toBe(1999);
    });

    it('prefers the channel-specific list over global', function () {
        $variant = ProductVariant::factory()->create();

        PriceList::create([
            'name' => 'Global', 'code' => 'g-'.uniqid(),
            'scope' => 'global', 'currency' => 'EUR', 'priority' => 100, 'active' => true,
        ])->prices()->create([
            'subject_type' => $variant->getMorphClass(),
            'subject_id' => $variant->id,
            'amount_minor' => 999,
            'currency' => 'EUR',
        ]);

        PriceList::create([
            'name' => 'Web', 'code' => 'w-'.uniqid(),
            'scope' => 'channel', 'scope_ref' => 'web', 'currency' => 'EUR',
            'priority' => 50, 'active' => true,
        ])->prices()->create([
            'subject_type' => $variant->getMorphClass(),
            'subject_id' => $variant->id,
            'amount_minor' => 1999,
            'currency' => 'EUR',
        ]);

        $price = app(PricingResolver::class)->resolve(
            variant: $variant,
            currency: 'EUR',
            channel: $this->makeChannel('web'),
        );
        expect($price->amount->amount)->toBe(1999);
    });

    it('applies tier pricing when quantity crosses min_quantity thresholds', function () {
        $variant = ProductVariant::factory()->create();

        PriceList::create([
            'name' => 'Tiered', 'code' => 'tier-'.uniqid(),
            'scope' => 'global', 'currency' => 'EUR', 'priority' => 100, 'active' => true,
        ])->prices()->createMany([
            ['subject_type' => $variant->getMorphClass(), 'subject_id' => $variant->id, 'amount_minor' => 1000, 'currency' => 'EUR', 'min_quantity' => 1],
            ['subject_type' => $variant->getMorphClass(), 'subject_id' => $variant->id, 'amount_minor' => 800, 'currency' => 'EUR', 'min_quantity' => 10],
            ['subject_type' => $variant->getMorphClass(), 'subject_id' => $variant->id, 'amount_minor' => 600, 'currency' => 'EUR', 'min_quantity' => 50],
        ]);

        $resolver = app(PricingResolver::class);

        expect($resolver->resolve(variant: $variant, currency: 'EUR', quantity: 5)->amount->amount)->toBe(1000);
        expect($resolver->resolve(variant: $variant, currency: 'EUR', quantity: 15)->amount->amount)->toBe(800);
        expect($resolver->resolve(variant: $variant, currency: 'EUR', quantity: 100)->amount->amount)->toBe(600);
    });

    it('honors compare_at_minor for strikethrough display', function () {
        $variant = ProductVariant::factory()->create();
        PriceList::create([
            'name' => 'Sale', 'code' => 'sale-'.uniqid(),
            'scope' => 'global', 'currency' => 'EUR', 'priority' => 100, 'active' => true,
        ])->prices()->create([
            'subject_type' => $variant->getMorphClass(),
            'subject_id' => $variant->id,
            'amount_minor' => 1299,
            'compare_at_minor' => 1999,
            'currency' => 'EUR',
        ]);

        $price = app(PricingResolver::class)->resolve(variant: $variant, currency: 'EUR');
        expect($price->amount->amount)->toBe(1299);
        expect($price->compareAt->amount)->toBe(1999);
        expect($price->isOnSale())->toBeTrue();
    });

    it('returns the variant base price when no list matches', function () {
        $variant = ProductVariant::factory()->create();
        // Provide a list with a different currency so it never matches.
        PriceList::create([
            'name' => 'Global', 'code' => 'usd-'.uniqid(),
            'scope' => 'global', 'currency' => 'USD', 'priority' => 100, 'active' => true,
        ]);

        $price = app(PricingResolver::class)->resolve(variant: $variant, currency: 'EUR');
        expect($price->amount->amount)->toBe(0);
    });

    it('localizes the rendered price', function () {
        $variant = ProductVariant::factory()->create();
        PriceList::create([
            'name' => 'EUR', 'code' => 'eur-'.uniqid(),
            'scope' => 'global', 'currency' => 'EUR', 'priority' => 100, 'active' => true,
        ])->prices()->create([
            'subject_type' => $variant->getMorphClass(),
            'subject_id' => $variant->id,
            'amount_minor' => 1999,
            'currency' => 'EUR',
        ]);

        $price = app(PricingResolver::class)->resolve(variant: $variant, currency: 'EUR', locale: 'en');
        expect($price->localized('en'))->toContain('€');
        expect($price->localized('fr'))->toContain(',');
    });
});
