<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Currency\CurrencyConverter;
use Headless\Accounting\Models\Currency;
use Headless\Accounting\Models\ExchangeRate;
use Headless\Accounting\MultiCurrency\CurrencyRevaluationService;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();

    Currency::firstOrCreate(['code' => 'USD'], ['symbol' => '$', 'decimals' => 2]);
    Currency::firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'decimals' => 2]);
    ExchangeRate::create(['base' => 'EUR', 'quote' => 'USD', 'rate' => 1.1, 'effective_at' => now()]);
});

describe('Multi-currency revaluation', function () {

    it('records a currency revaluation snapshot', function () {
        $co = Company::create(['code' => 'MC', 'name' => 'Multi Co', 'base_currency' => 'EUR']);
        $rev = (new CurrencyRevaluationService(
            app(CurrencyConverter::class),
            app(Journal::class),
        ))->execute($co->id, 'USD', CarbonImmutable::now());

        expect($rev->currency)->toBe('USD');
        expect($rev->as_of->toDateString())->toBe(CarbonImmutable::now()->toDateString());
        expect($rev->breakdown)->toBeArray();
    });
});
