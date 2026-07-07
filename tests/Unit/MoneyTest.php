<?php

declare(strict_types=1);

use Headless\Accounting\Currency\Money;

describe('Money value object', function () {

    it('stores amount as integer minor units', function () {
        $m = new Money(1234, 'EUR');
        expect($m->amount)->toBe(1234);
        expect($m->currency)->toBe('EUR');
    });

    it('rejects non-ISO-4217 currency codes', function () {
        new Money(1, 'eur');   // lowercase accepted, we normalize uppercase internal use
        expect(fn () => new Money(1, 'EURO'))->toThrow(InvalidArgumentException::class);
        expect(fn () => new Money(1, ''))->toThrow(InvalidArgumentException::class);
    });

    it('rejects out-of-range amounts', function () {
        expect(fn () => new Money(PHP_INT_MAX, 'EUR'))->toThrow(InvalidArgumentException::class);
    });

    it('adds and subtracts within the same currency', function () {
        $a = new Money(500, 'EUR');
        $b = new Money(300, 'EUR');

        expect($a->add($b)->amount)->toBe(800);
        expect($a->subtract($b)->amount)->toBe(200);
    });

    it('throws on currency mismatch in arithmetic', function () {
        expect(fn () => (new Money(100, 'EUR'))->add(new Money(50, 'USD')))->toThrow(InvalidArgumentException::class);
        expect(fn () => (new Money(100, 'EUR'))->subtract(new Money(50, 'USD')))->toThrow(InvalidArgumentException::class);
        expect(fn () => (new Money(100, 'EUR'))->compare(new Money(50, 'USD')))->toThrow(InvalidArgumentException::class);
    });

    it('multiplies by integer factor', function () {
        $m = new Money(150, 'EUR');
        expect($m->multiply(3)->amount)->toBe(450);
    });

    it('computes percentage with banker rounding by default', function () {
        $m = new Money(1000, 'EUR');

        // 10% of 1000 = 100 (exact)
        expect($m->percentage(10.0)->amount)->toBe(100);

        // 33.33% of 9999 = 3332.6667 → banker's round to 3333
        expect((new Money(9999, 'EUR'))->percentage(33.33)->amount)->toBe(3333);

        // half-up → 3333 as well
        expect((new Money(9999, 'EUR'))->percentage(33.33, 'half_up')->amount)->toBe(3333);
    });

    it('builds money from float with explicit rounding modes', function () {
        expect(Money::fromFloat(0.5, 'EUR')->amount)->toBe(50);    // banker → even
        expect(Money::fromFloat(0.5, 'EUR', 2, 'half_up')->amount)->toBe(50);
        expect(Money::fromFloat(0.5, 'EUR', 2, 'up')->amount)->toBe(50);
        expect(Money::fromFloat(0.5, 'EUR', 2, 'down')->amount)->toBe(50);

        expect(Money::fromFloat(1.005, 'EUR')->amount)->toBe(101); // 100.5 → even → 100 (banker)
        expect(Money::fromFloat(1.005, 'EUR', 2, 'half_up')->amount)->toBe(101);
    });

    it('allocates a sum into N equal shares, distributing the remainder correctly', function () {
        // 100 / 3 = 33 each, +1 to first share → 34, 33, 33
        $parts = (new Money(100, 'EUR'))->allocate(3);
        expect(count($parts))->toBe(3);
        expect(array_sum(array_map(fn ($p) => $p->amount, $parts)))->toBe(100);

        // Negative allocation: -100 / 3 = -33 each, -1 to first → -34, -33, -33
        $partsNeg = (new Money(-100, 'EUR'))->allocate(3);
        expect(array_sum(array_map(fn ($p) => $p->amount, $partsNeg)))->toBe(-100);
    });

    it('reports signed state correctly', function () {
        expect((new Money(0, 'EUR'))->isZero())->toBeTrue();
        expect((new Money(0, 'EUR'))->isPositive())->toBeFalse();
        expect((new Money(0, 'EUR'))->isNegative())->toBeFalse();

        expect((new Money(5, 'EUR'))->isPositive())->toBeTrue();
        expect((new Money(-5, 'EUR'))->isNegative())->toBeTrue();
    });

    it('negates and absolute-values correctly', function () {
        $m = new Money(100, 'EUR');
        expect($m->negate()->amount)->toBe(-100);
        expect((new Money(-100, 'EUR'))->abs()->amount)->toBe(100);
        expect($m->abs()->amount)->toBe(100);
    });

    it('compares within same currency', function () {
        $a = new Money(100, 'EUR');
        $b = new Money(100, 'EUR');
        $c = new Money(50, 'EUR');
        $d = new Money(200, 'EUR');

        expect($a->compare($b))->toBe(0);
        expect($c->compare($a))->toBe(-1);
        expect($d->compare($a))->toBe(1);
    });

    it('formats with locale-aware separators', function () {
        $m = new Money(123456, 'EUR');

        // EN: "1,234.56 €"
        expect($m->format('en'))->toBe('1,234.56 €');

        // FR: narrow no-break thousands + comma decimal
        $fr = $m->format('fr');
        expect($fr)->toContain("\u{202F}");
        expect($fr)->toContain(',');
        expect($fr)->toContain('€');
    });

    it('zero factory returns zero amount', function () {
        $z = Money::zero('EUR');
        expect($z->amount)->toBe(0);
        expect($z->isZero())->toBeTrue();
    });

    it('toString format mirrors format()', function () {
        $m = new Money(500, 'EUR');
        expect((string) $m)->toBe($m->format('en'));
    });
});
