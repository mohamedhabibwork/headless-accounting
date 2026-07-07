<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Budget\Budget;
use Headless\Accounting\Budget\BudgetLine;
use Headless\Accounting\Budget\BudgetVsActualService;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();
});

describe('Budget vs Actual', function () {
    it('produces a budget-vs-actual report', function () {
        $co = Company::create(['code' => 'BG', 'name' => 'Budget Co', 'base_currency' => 'EUR']);
        $expenseAccount = Account::where('code', '5000')->firstOrFail();

        $budget = Budget::create([
            'company_id' => $co->id, 'code' => 'B-2025',
            'name' => '2025 OpEx', 'period' => '2025-01-01..2025-12-31',
            'currency' => 'EUR',
        ]);

        BudgetLine::create([
            'budget_id' => $budget->id, 'account_id' => $expenseAccount->id,
            'monthly_amount_minor' => 100000, 'months' => 12,
        ]);

        $report = (new BudgetVsActualService)->execute($co->id, $budget, 'EUR');

        expect($report['budget_total_minor'])->toBe(100000 * 12);
        expect($report['lines'])->toBeArray();
    });
});
