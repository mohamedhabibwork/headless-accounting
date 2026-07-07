<?php

declare(strict_types=1);

use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Support\NumberGenerator;
use Headless\Accounting\Support\Numbers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

describe('Numbers helpers', function () {

    it('rounds half-even by default', function () {
        expect(Numbers::roundHalfEven(5))->toBe(5);
        expect(Numbers::roundHalfEven(100, 0))->toBe(100);
    });

    it('rounds half-up when requested', function () {
        expect(Numbers::roundHalfUp(101))->toBe(101);
    });

    it('computes percentages with every rounding mode', function () {
        expect(Numbers::percent(10000, 12.5))->toBe(1250);              // exact
        expect(Numbers::percent(10001, 12.5, 'half_even'))->toBe(1250);
        expect(Numbers::percent(10001, 12.5, 'half_up'))->toBe(1251);
        expect(Numbers::percent(100, 25, 'down'))->toBe(25);
        expect(Numbers::percent(100, 25, 'up'))->toBe(25);
    });

    it('rejects unknown rounding modes', function () {
        expect(fn () => Numbers::percent(100, 10, 'bogus'))->toThrow(InvalidArgumentException::class);
    });
});

describe('NumberGenerator', function () {

    it('produces a per-year sequential id', function () {
        $class = new class extends Model
        {
            use SoftDeletes;

            public $timestamps = true;

            protected $table = 'ha_orders';

            protected $guarded = [];
        };

        Order::create([
            'number' => NumberGenerator::next('ORD', Order::class),
            'state' => Order::STATE_CART,
            'currency' => 'EUR',
            'channel_code' => 'web',
            'locale' => 'en',
        ]);

        $first = NumberGenerator::next('ORD', Order::class);
        $second = NumberGenerator::next('ORD', Order::class);

        expect($first)->toMatch('/^ORD-\d{4}-\d{6}$/');
        expect($second)->not->toBe($first);
    });

    it('produces a per-day sequential id', function () {
        $today = NumberGenerator::daily('JE', JournalEntry::class);
        expect($today)->toMatch('/^JE-\d{8}-\d{5}$/');
    });
});
