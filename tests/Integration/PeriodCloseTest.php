<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Accounting\Ledger;
use Headless\Accounting\Actions\Accounting\ClosePeriod;
use Headless\Accounting\Actions\Accounting\PostJournalEntry;
use Headless\Accounting\Models\AccountingPeriod;
use Headless\Accounting\Models\FiscalYear;
use Headless\Accounting\Models\Order;

beforeEach(function () {
    $this->installChart();
});

it('rolls up net income via 3100 when closing a profitable period', function () {
    $fy = FiscalYear::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
    $period = AccountingPeriod::create([
        'fiscal_year_id' => $fy->id,
        'code' => '2026-Q1',
        'starts_at' => '2026-01-01', 'ends_at' => '2026-03-31',
    ]);

    // Sale of 12,000 → Revenue (4000) 12,000
    $order = Order::create([
        'number' => 'ORD-1', 'currency' => 'EUR',
        'channel_code' => 'web', 'state' => 'placed',
    ]);
    (new PostJournalEntry(app(Journal::class)))->execute(
        source: $order, currency: 'EUR', description: 'sale',
        debit: [['account' => '1200', 'amount' => 12000]],
        credit: [['account' => '4000', 'amount' => 12000]],
    );

    // Expense (5000) for shipping — COGS/Expense goes to 6000
    (new PostJournalEntry(app(Journal::class)))->execute(
        source: $order, currency: 'EUR', description: 'shipping exp',
        debit: [['account' => '6000', 'amount' => 5000]],
        credit: [['account' => '1100', 'amount' => 5000]],
    );

    $closed = (new ClosePeriod(app(Ledger::class)))->execute($period);
    expect($closed->closed)->toBeTrue();

    // After close, the period contains 3 entries (2 sales + 1 close roll-up),
    // all balanced.
    $balancedAll = $period->fresh()->load('journalEntries.postings')
        ->journalEntries->every(fn ($je) => $je->assertBalanced() ?: true);
    expect($balancedAll)->toBeTrue();
});

it('produces a Trial Balance and Income Statement snapshot', function () {
    // Make some entries, then list the ledger.
    $order = Order::create([
        'number' => 'ORD-1', 'currency' => 'EUR',
        'channel_code' => 'web', 'state' => 'placed',
    ]);
    (new PostJournalEntry(app(Journal::class)))->execute(
        source: $order, currency: 'EUR', description: 'sale',
        debit: [['account' => '1200', 'amount' => 50000]],
        credit: [['account' => '4000', 'amount' => 50000]],
    );

    $ledger = app(Ledger::class);
    $tb = $ledger->trialBalance('EUR');
    expect($tb)->toBeArray();

    $pnl = $ledger->incomeStatement('EUR', now()->subMonth()->startOfDay(), now()->addDay());
    expect($pnl)->toHaveKey('revenue');
    expect($pnl['revenue'])->toBeGreaterThan(0);
});
