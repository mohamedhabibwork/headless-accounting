<?php

declare(strict_types=1);

use Headless\Accounting\Currency\Currency;

describe('Currency registry', function () {

    it('knows the built-in ISO-4217 codes', function () {
        expect(Currency::codes())->toContain('EUR', 'USD', 'GBP', 'JPY');
    });

    it('looks up by uppercase code', function () {
        $eur = Currency::get('EUR');
        expect($eur['symbol'])->toBe('€');
        expect($eur['decimals'])->toBe(2);
        expect(Currency::get('eur')['symbol'])->toBe('€');
    });

    it('returns null for unknown codes', function () {
        expect(Currency::get('XYZ'))->toBeNull();
        expect(Currency::exists('XYZ'))->toBeFalse();
    });

    it('returns correct decimal places', function () {
        expect(Currency::decimals('EUR'))->toBe(2);
        expect(Currency::decimals('JPY'))->toBe(0);
    });

    it('returns the symbol', function () {
        expect(Currency::symbol('EUR'))->toBe('€');
        expect(Currency::symbol('JPY'))->toBe('¥');
    });

    it('uses the right separators per locale', function () {
        expect(Currency::decimalSeparator('en'))->toBe('.');
        expect(Currency::decimalSeparator('fr'))->toBe(',');
        expect(Currency::decimalSeparator('ja'))->toBe('.');

        expect(Currency::thousandsSeparator('en'))->toBe(',');
        expect(Currency::thousandsSeparator('fr'))->toBe("\u{202F}");
    });

    it('allows registering new currencies at runtime', function () {
        Currency::register('BCH', 'Bitcoin Cash', 'Ƀ', decimals: 8);
        expect(Currency::exists('BCH'))->toBeTrue();
        expect(Currency::get('BCH')['symbol'])->toBe('Ƀ');
        expect(Currency::decimals('BCH'))->toBe(8);
    });

    it('falls back to the code for unknown symbols', function () {
        expect(Currency::symbol('ZZZ'))->toBe('ZZZ');
    });
});
