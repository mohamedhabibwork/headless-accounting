<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Reporting\FinancialStatements;
use Headless\Accounting\Reporting\TaxReports;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();
});

describe('Reporting', function () {
    it('renders a balance sheet even with no transactions', function () {
        $co = Company::create(['code' => 'R', 'name' => 'Reports Co', 'base_currency' => 'EUR']);
        $bs = (new FinancialStatements)->balanceSheet($co->id, 'EUR', CarbonImmutable::now());

        expect($bs['currency'])->toBe('EUR');
        expect($bs)->toHaveKeys(['assets', 'liabilities', 'equity', 'lines']);
    });

    it('renders a VAT summary report', function () {
        $co = Company::create(['code' => 'R', 'name' => 'Reports Co', 'base_currency' => 'EUR']);
        $vat = (new TaxReports)->vatReport($co->id, CarbonImmutable::now()->subMonth(), CarbonImmutable::now());

        expect($vat['currency'])->toBe('EUR');
        expect($vat)->toHaveKeys(['taxable_sales', 'output_vat', 'effective_rate_pct']);
    });
});
