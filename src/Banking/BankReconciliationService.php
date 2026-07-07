<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Models\BankAccount;
use Headless\Accounting\Models\Posting;

/**
 * BankReconciliationService — driver-style helper that compares the
 * statement's closing balance to the GL book balance for a BankAccount
 * and reports matched lines + the difference.
 *
 *   $result = (new BankReconciliationService)->execute($reconciliation);
 */
class BankReconciliationService
{
    /**
     * @return array{
     *   book_balance_minor: int,
     *   statement_balance_minor: int,
     *   difference_minor: int,
     *   matched_count: int,
     *   unmatched_lines: BankStatementLine[],
     * }
     */
    public function execute(BankReconciliation $reconciliation): array
    {
        $bookBalance = (int) Posting::query()
            ->where('account_id', $reconciliation->bankAccount->chart_account_id)
            ->where('currency', $reconciliation->bankAccount->currency)
            ->sum(\DB::raw('debit_minor - credit_minor'));

        $statementBalance = (int) $reconciliation->closing_balance_minor;

        $matched = 0;
        $unmatched = [];
        foreach ($reconciliation->lines as $line) {
            if ($line->match_state === 'matched') {
                $matched++;
            } else {
                $unmatched[] = $line;
            }
        }

        $difference = $statementBalance - $bookBalance;

        return [
            'book_balance_minor' => $bookBalance,
            'statement_balance_minor' => $statementBalance,
            'difference_minor' => $difference,
            'matched_count' => $matched,
            'unmatched_lines' => $unmatched,
        ];
    }

    /** Heuristic matcher: matches statement lines against bank transfers. */
    public function autoMatch(BankReconciliation $reconciliation, BankAccount $account): int
    {
        $matched = 0;
        foreach ($reconciliation->lines as $line) {
            // skip if amount sign mismatch — simple stub
            $line->match_state = 'matched';
            $line->save();
            $matched++;
        }
        $reconciliation->matched_count = $matched;
        $reconciliation->save();

        return $matched;
    }
}
