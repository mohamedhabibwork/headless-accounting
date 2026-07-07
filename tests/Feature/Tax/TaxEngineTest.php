<?php

declare(strict_types=1);

use Headless\Accounting\Models\Address;
use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\TaxClass;
use Headless\Accounting\Tax\TaxEngine;

function taxable_variant(TaxClass $class): ProductVariant
{
    $product = Product::factory()->create(['tax_class_id' => $class->id]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $variant->setRelation('product', $product);

    return $variant;
}

describe('TaxEngine', function () {

    it('resolves a zone from country match', function () {
        $zone = $this->makeTaxZone('eu-vat', 'EU', ['FR', 'DE', 'IT']);
        $this->makeTaxRate($zone, $this->makeTaxClass(), 20.0);

        $engine = new TaxEngine(['inclusive' => false]);
        $breakdown = $engine->resolve(
            subject: taxable_variant($zone->rates()->first()->taxClass),
            subtotalMinor: 10000,
            currency: 'EUR',
            shipTo: new Address(['country_code' => 'FR']),
        );

        expect($breakdown->total()->amount)->toBe(2000);
    });

    it('matches zone from postal_code pattern', function () {
        $zone = $this->makeTaxZone('fr-dom', 'FR Overseas');
        $zone->members()->create(['country_code' => 'FR', 'postal_code_pattern' => '97*', 'operator' => 'and']);
        $this->makeTaxRate($zone, $this->makeTaxClass(), 2.1);

        $engine = new TaxEngine(['inclusive' => false]);

        $breakdown = $engine->resolve(
            subject: taxable_variant($zone->rates()->first()->taxClass),
            subtotalMinor: 10000,
            currency: 'EUR',
            shipTo: new Address(['country_code' => 'FR', 'postal_code' => '97400']),
        );
        expect($breakdown->total()->amount)->toBe(210);

        // Out of pattern → no zone → no tax
        $breakdown2 = $engine->resolve(
            subject: taxable_variant($zone->rates()->first()->taxClass),
            subtotalMinor: 10000,
            currency: 'EUR',
            shipTo: new Address(['country_code' => 'FR', 'postal_code' => '75001']),
        );
        expect($breakdown2->lines())->toBe([]);
    });

    it('applies compound tax (tax on tax)', function () {
        $zone = $this->makeTaxZone('multi', 'Multi');
        $standard = $this->makeTaxClass('standard', 'Standard');
        $rate1 = $this->makeTaxRate($zone, $standard, 10.0);   // base
        $rate2 = $this->makeTaxRate($zone, $standard, 5.0, compound: true);

        $engine = new TaxEngine(['inclusive' => false]);
        $breakdown = $engine->resolve(
            subject: taxable_variant($standard),
            subtotalMinor: 10000,
            currency: 'EUR',
            shipTo: new Address(['country_code' => 'X']),
        );

        expect($breakdown->lines())->toHaveCount(2);
        // Non-compound first: 1000
        expect($breakdown->lines()[0]->amount->amount)->toBe(1000);
        // Compound on (subtotal + first tax): 10000 + 1000 = 11000, 5% = 550
        expect($breakdown->lines()[1]->amount->amount)->toBe(550);
        expect($breakdown->total()->amount)->toBe(1550);
    });

    it('emits inclusive totals (gross == subtotal)', function () {
        $zone = $this->makeTaxZone('incl', 'Incl');
        $class = $this->makeTaxClass();
        $this->makeTaxRate($zone, $class, 20.0);

        $engine = new TaxEngine(['inclusive' => true]);
        $breakdown = $engine->resolve(
            subject: taxable_variant($class),
            subtotalMinor: 12000,
            currency: 'EUR',
            shipTo: new Address(['country_code' => 'X']),
        );

        expect($breakdown->gross()->amount)->toBe(12000);  // already inclusive
        expect($breakdown->total()->amount)->toBe(2000);
    });

    it('returns an empty breakdown when no zone matches', function () {
        $zone = $this->makeTaxZone('xx', 'X');
        $zone->members()->create(['country_code' => 'YY']);
        $this->makeTaxRate($zone, $this->makeTaxClass(), 5.0);

        $engine = new TaxEngine(['inclusive' => false]);
        $breakdown = $engine->resolve(
            subject: taxable_variant(TaxClass::query()->first()),
            subtotalMinor: 1000,
            currency: 'EUR',
            shipTo: new Address(['country_code' => 'ZZ']),
        );

        expect($breakdown->lines())->toBe([]);
        expect($breakdown->total()->amount)->toBe(0);
    });

    it('respects tax_class filter on rates', function () {
        $zone = $this->makeTaxZone('tc', 'TC');
        $stdClass = $this->makeTaxClass('standard');
        $zeroClass = $this->makeTaxClass('zero');
        $this->makeTaxRate($zone, $stdClass, 20.0);
        $this->makeTaxRate($zone, $zeroClass, 0.0);

        $engine = new TaxEngine(['inclusive' => false]);
        $breakdown = $engine->resolve(
            subject: taxable_variant($stdClass),
            subtotalMinor: 5000,
            currency: 'EUR',
            shipTo: new Address(['country_code' => 'X']),
        );
        expect($breakdown->lines())->toHaveCount(1);             // only std, zero filtered out
        expect($breakdown->total()->amount)->toBe(1000);
    });
});
