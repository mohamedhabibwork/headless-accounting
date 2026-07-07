<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Accounting;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\UnbalancedJournalException;
use Headless\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * PostJournalEntry — explicit form of {@see Journal::post()} that
 * performs full validation: balanced postings, real currency,
 * auto-balance counter-entry for asymmetries.
 *
 *   $entry = (new PostJournalEntry($journal))->execute(
 *       source: $order,
 *       currency: 'EUR',
 *       description: 'Order ORD-2026-000123 placed',
 *       debit:  [['account' => '1200', 'amount' => 9999]],
 *       credit: [['account' => '4000', 'amount' => 8260], ['account' => '2100', 'amount' => 1739]],
 *   );
 */
final class PostJournalEntry extends Action
{
    public function __construct(private readonly Journal $journal) {}

    protected function handle(
        Model $source,
        string $currency,
        string $description = '',
        array $debit = [],
        array $credit = [],
        bool $autoPosted = true,
    ): JournalEntry {
        $rows = [];
        $debitTotal = 0;
        $creditTotal = 0;

        foreach ($debit as $r) {
            $debitTotal += (int) ($r['amount'] ?? 0);
            $rows[] = [
                'account' => $r['account'] ?? ($r['account_id'] ?? null),
                'account_id' => $r['account_id'] ?? null,
                'debit' => (int) ($r['amount'] ?? 0),
                'credit' => 0,
                'currency' => $r['currency'] ?? $currency,
                'memo' => $r['memo'] ?? null,
            ];
        }
        foreach ($credit as $r) {
            $creditTotal += (int) ($r['amount'] ?? 0);
            $rows[] = [
                'account' => $r['account'] ?? ($r['account_id'] ?? null),
                'account_id' => $r['account_id'] ?? null,
                'debit' => 0,
                'credit' => (int) ($r['amount'] ?? 0),
                'currency' => $r['currency'] ?? $currency,
                'memo' => $r['memo'] ?? null,
            ];
        }

        if ($debitTotal !== $creditTotal) {
            throw new UnbalancedJournalException(
                "Journal entry is unbalanced: debit={$debitTotal} credit={$creditTotal}."
            );
        }

        return $this->journal->post(
            source: $source,
            postings: $rows,
            currency: $currency,
            description: $description,
            autoPosted: $autoPosted,
        );
    }
}
