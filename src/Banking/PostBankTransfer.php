<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Models\JournalEntry;

/**
 * PostBankTransfer — translates a {@see BankTransfer} into a balanced
 * journal entry: debit the destination GL account, credit the source.
 * If a fee is present, also post a separate bank-fee entry.
 */
class PostBankTransfer
{
    public function __construct(private readonly Journal $journal) {}

    public function execute(BankTransfer $transfer): JournalEntry
    {
        $entry = $this->journal->post(
            source: $transfer,
            currency: $transfer->currency,
            description: 'Bank transfer '.$transfer->reference,
            autoPosted: true,
            postings: [
                ['account' => $transfer->toAccount->chart_account_id, 'debit' => $transfer->amount_minor, 'memo' => 'Bank in'],
                ['account' => $transfer->fromAccount->chart_account_id, 'credit' => $transfer->amount_minor, 'memo' => 'Bank out'],
            ],
        );

        if ((int) $transfer->fee_minor > 0) {
            $this->journal->post(
                source: $transfer,
                currency: $transfer->currency,
                description: 'Bank transfer fee',
                autoPosted: true,
                postings: [
                    ['account' => '9000', 'debit' => (int) $transfer->fee_minor, 'memo' => 'Bank fee'],
                    ['account' => $transfer->fromAccount->chart_account_id, 'credit' => (int) $transfer->fee_minor, 'memo' => 'Fee deducted'],
                ],
            );
        }

        $transfer->update([
            'state' => 'posted',
            'journal_entry_id' => $entry->id,
        ]);

        return $entry;
    }
}
