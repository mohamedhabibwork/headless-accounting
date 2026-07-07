<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Banking\BankAccount;
use Headless\Accounting\Banking\BankTransfer;
use Headless\Accounting\Banking\CashPositionSnapshot;
use Headless\Accounting\Banking\PostBankTransfer;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();
});

describe('Bank transfers', function () {
    it('posts a balanced journal entry + fee entry', function () {
        $company = Company::create(['code' => 'BK', 'name' => 'Bank Co', 'base_currency' => 'EUR']);

        $checking = Account::where('code', '1100')->firstOrFail();
        $savings = Account::where('code', '1200')->firstOrFail();

        $bank1 = BankAccount::create([
            'company_id' => $company->id, 'code' => 'CHK', 'name' => 'Checking',
            'chart_account_id' => $checking->id, 'currency' => 'EUR',
        ]);
        $bank2 = BankAccount::create([
            'company_id' => $company->id, 'code' => 'SAV', 'name' => 'Savings',
            'chart_account_id' => $savings->id, 'currency' => 'EUR',
        ]);

        $transfer = BankTransfer::create([
            'company_id' => $company->id,
            'from_account_id' => $bank1->id,
            'to_account_id' => $bank2->id,
            'currency' => 'EUR',
            'amount_minor' => 10000,
            'fee_minor' => 250,
            'transfer_date' => now()->toDateString(),
            'reference' => 'T-1',
        ]);

        (new PostBankTransfer(app(Journal::class)))->execute($transfer);

        expect($transfer->fresh()->state)->toBe('posted');
        expect($transfer->journal_entry_id)->not->toBeNull();
    });

    it('computes a daily cash position', function () {
        $company = Company::create(['code' => 'BK', 'name' => 'Bank Co', 'base_currency' => 'EUR']);
        $result = (new CashPositionSnapshot)->execute($company->id, 'EUR');
        expect($result['currency'])->toBe('EUR');
        expect($result['snapshot'])->toBeArray();
    });
});
