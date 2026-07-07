<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Accounting;

use Carbon\CarbonImmutable;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Posting;
use Headless\Accounting\Support\Config;

/**
 * ReconcileAccount — verifies postings balance against external
 * statements and emits any necessary adjustment journals.
 *
 * In practice this is the "month end" action an admin runs once the
 * bank statement is in. The `expected_balance_minor` is the closing
 * balance per the statement; the action computes the delta and, if
 * it differs, posts an adjustment using the configured variance
 * account (default: 9000 — Bank Fees).
 */
final class ReconcileAccount extends Action
{
    public function __construct(private readonly Journal $journal) {}

    protected function handle(
        Account $account,
        int $expectedBalanceMinor,
        ?CarbonImmutable $asOf = null,
        string $varianceAccountCode = '9000',
        string $currency = '',
    ): array {
        $currency ??= (string) ($account->currency ?: Config::get('headless-accounting.accounting.default_currency'));
        $asOf ??= CarbonImmutable::now();

        $actualBalance = (int) Posting::query()
            ->where('account_id', $account->id)
            ->where('currency', $currency)
            ->where('created_at', '<=', $asOf)
            ->selectRaw('SUM(debit_minor) - SUM(credit_minor) as bal')
            ->value('bal');

        $delta = $expectedBalanceMinor - (int) $actualBalance;
        $entry = null;
        if ($delta !== 0) {
            $entry = $this->journal->post(
                source: $account,
                currency: $currency,
                description: "Reconciliation adjustment for {$account->code} ({$account->name})",
                autoPosted: false,
                postings: [
                    ['account' => $account->code,           'debit' => $delta > 0 ? $delta : 0, 'credit' => $delta < 0 ? abs($delta) : 0, 'memo' => 'Reconciliation variance'],
                    ['account' => $varianceAccountCode,     'debit' => $delta < 0 ? abs($delta) : 0, 'credit' => $delta > 0 ? $delta : 0, 'memo' => 'Variance'],
                ],
            );
        }

        return [
            'account_id' => $account->id,
            'expected_minor' => $expectedBalanceMinor,
            'actual_minor' => (int) $actualBalance,
            'delta_minor' => $delta,
            'is_reconciled' => $delta === 0,
            'adjustment_entry_id' => $entry?->id,
        ];
    }
}
