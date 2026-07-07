<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\HR\PayrollCalculator;
use Headless\Accounting\HR\PayrollPeriod;
use Headless\Accounting\Models\Employee;
use Headless\Accounting\Models\SalaryComponent;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();
});

describe('Payroll run', function () {
    it('computes gross / tax / net for an employee', function () {
        $co = Company::create(['code' => 'PR', 'name' => 'Payroll Co', 'base_currency' => 'EUR']);

        $emp = Employee::create([
            'company_id' => $co->id, 'code' => 'E1',
            'name' => 'Alice', 'currency' => 'EUR',
            'monthly_gross_minor' => 300000, 'tax_rate' => 0.2,
        ]);

        SalaryComponent::create([
            'company_id' => $co->id, 'code' => 'BONUS', 'name' => 'Bonus',
            'kind' => 'earning', 'amount_minor' => 50000, 'active' => true,
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $co->id, 'code' => '2025-01',
            'name' => 'Jan 2025',
            'start_date' => '2025-01-01', 'end_date' => '2025-01-31',
        ]);

        $result = (new PayrollCalculator)->calculate($emp, $period);

        expect($result['gross_minor'])->toBe(300000);
        expect($result['tax_minor'])->toBe(60000);
        expect($result['net_minor'])->toBe(240000);
    });
});
