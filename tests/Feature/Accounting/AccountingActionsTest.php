<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Accounting\Ledger;
use Headless\Accounting\Actions\Accounting\ClosePeriod;
use Headless\Accounting\Actions\Accounting\PostJournalEntry;
use Headless\Accounting\Actions\Accounting\ReconcileAccount;
use Headless\Accounting\Exceptions\InvalidTransitionException;
use Headless\Accounting\Exceptions\UnbalancedJournalException;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\AccountingPeriod;
use Headless\Accounting\Models\FiscalYear;
use Headless\Accounting\Models\Order;

beforeEach(function () {
    $this->installChart();
});

describe('PostJournalEntry action', function () {
    it('persists a balanced entry and posts two ledger rows', function () {
        $order = Order::create([
            'number' => 'ORD-1', 'currency' => 'EUR',
            'channel_code' => 'web', 'state' => 'placed',
        ]);

        $entry = (new PostJournalEntry(app(Journal::class)))->execute(
            source: $order, currency: 'EUR', description: 'revenue',
            debit: [['account' => '1200', 'amount' => 1000, 'memo' => 'AR']],
            credit: [['account' => '4000', 'amount' => 1000, 'memo' => 'Sale']],
        );

        expect($entry->postings()->count())->toBe(2);
        $entry->assertBalanced();
    });

    it('rejects unbalanced entries', function () {
        $order = Order::create([
            'number' => 'ORD-1', 'currency' => 'EUR',
            'channel_code' => 'web', 'state' => 'placed',
        ]);

        expect(fn () => (new PostJournalEntry(app(Journal::class)))->execute(
            source: $order, currency: 'EUR', description: 'broken',
            debit: [['account' => '1200', 'amount' => 1000]],
            credit: [['account' => '4000', 'amount' => 500]],
        ))->toThrow(UnbalancedJournalException::class);
    });
});

describe('ReconcileAccount action', function () {
    it('reports matched balance when expected == actual', function () {
        $order = Order::create([
            'number' => 'ORD-1', 'currency' => 'EUR',
            'channel_code' => 'web', 'state' => 'placed',
        ]);
        (new PostJournalEntry(app(Journal::class)))->execute(
            source: $order, currency: 'EUR',
            debit: [['account' => '1100', 'amount' => 5000]],
            credit: [['account' => '1200', 'amount' => 5000]],
        );

        $result = (new ReconcileAccount(app(Journal::class)))->execute(
            account: Account::query()->where('code', '1100')->first(),
            expectedBalanceMinor: 5000,
            currency: 'EUR',
        );
        expect($result['is_reconciled'])->toBeTrue();
        expect($result['delta_minor'])->toBe(0);
    });

    it('posts an adjustment when expected != actual', function () {
        $order = Order::create([
            'number' => 'ORD-1', 'currency' => 'EUR',
            'channel_code' => 'web', 'state' => 'placed',
        ]);
        (new PostJournalEntry(app(Journal::class)))->execute(
            source: $order, currency: 'EUR',
            debit: [['account' => '1100', 'amount' => 5000]],
            credit: [['account' => '1200', 'amount' => 5000]],
        );

        $result = (new ReconcileAccount(app(Journal::class)))->execute(
            account: Account::query()->where('code', '1100')->first(),
            expectedBalanceMinor: 4990,    // bank says 4990, books say 5000
            currency: 'EUR',
        );

        expect($result['is_reconciled'])->toBeFalse();
        expect($result['delta_minor'])->toBe(-10);
        expect($result['adjustment_entry_id'])->not->toBeNull();
    });
});

describe('ClosePeriod action', function () {
    it('closes a period once all entries are balanced', function () {
        $fy = FiscalYear::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $period = AccountingPeriod::create([
            'fiscal_year_id' => $fy->id,
            'code' => '2026-Q1',
            'starts_at' => '2026-01-01', 'ends_at' => '2026-03-31',
        ]);

        $order = Order::create([
            'number' => 'ORD-1', 'currency' => 'EUR',
            'channel_code' => 'web', 'state' => 'placed',
        ]);
        (new PostJournalEntry(app(Journal::class)))->execute(
            source: $order, currency: 'EUR',
            debit: [['account' => '1100', 'amount' => 2000]],
            credit: [['account' => '4000', 'amount' => 2000]],
        );

        $closed = (new ClosePeriod(app(Ledger::class)))->execute($period, 'month end');
        expect($closed->closed)->toBeTrue();
    });

    it('refuses to close an already-closed period', function () {
        $fy = FiscalYear::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $period = AccountingPeriod::create([
            'fiscal_year_id' => $fy->id, 'code' => '2026-Q2',
            'starts_at' => '2026-04-01', 'ends_at' => '2026-06-30', 'closed' => true,
        ]);

        expect(fn () => (new ClosePeriod(app(Ledger::class)))->execute($period))
            ->toThrow(InvalidTransitionException::class);
    });
});
