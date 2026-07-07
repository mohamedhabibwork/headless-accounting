<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Accounting;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Accounting\Ledger;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Exceptions\InvalidTransitionException;
use Headless\Accounting\Models\AccountingPeriod;
use Headless\Accounting\Support\Config;

/**
 * ClosePeriod — closes an {@see AccountingPeriod} so no more journal
 * entries can be posted against it. Validates that all of the period's
 * entries are balanced before flipping the flag.
 */
final class ClosePeriod extends Action
{
    public function __construct(private readonly Ledger $ledger) {}

    protected function handle(AccountingPeriod $period, ?string $reason = null): AccountingPeriod
    {
        if ($period->closed) {
            throw new InvalidTransitionException("Period {$period->code} is already closed.");
        }

        // Validate every entry in the period is balanced.
        $period->load('journalEntries.postings');
        foreach ($period->journalEntries as $entry) {
            $debit = (int) $entry->postings->sum('debit_minor');
            $credit = (int) $entry->postings->sum('credit_minor');
            if ($debit !== $credit) {
                throw new AccountingException("Journal entry {$entry->number} is not balanced.");
            }
        }

        // Roll up retained earnings into the equity account 3100 if the
        // application defines a P&L period (closing for net income).
        $from = $period->starts_at;
        $to = $period->ends_at;
        $pnl = $this->ledger->incomeStatement(Config::get('headless-accounting.accounting.default_currency'), $from, $to);
        $net = (int) ($pnl['net'] ?? 0);

        if ($net !== 0) {
            // We need Journal inside; resolve from container.
            $journal = app(Journal::class);
            $journal->post(
                source: $period,
                currency: Config::get('headless-accounting.accounting.default_currency'),
                description: "Period close: roll up net income for {$period->code}",
                autoPosted: true,
                postings: [
                    ['account' => $net > 0 ? '3100' : '3000', 'debit' => $net < 0 ? abs($net) : 0, 'credit' => $net > 0 ? $net : 0, 'memo' => 'Net income roll-up'],
                    ['account' => '3100',                     'debit' => $net > 0 ? $net : 0,         'credit' => $net < 0 ? abs($net) : 0, 'memo' => 'Counter-entry to balance'],
                ],
            );
        }

        $period->closed = true;
        $period->save();
        $period->recordEvent?->delete();  // best-effort; Period doesn't carry a record

        return $period;
    }
}
