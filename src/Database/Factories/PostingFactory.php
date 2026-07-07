<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Posting;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostingFactory extends Factory
{
    protected $model = Posting::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'debit_minor' => 0,
            'credit_minor' => 0,
            'currency' => 'EUR',
            'memo' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function forJournalEntry(int $journalEntryId): static
    {
        return $this->state(['journal_entry_id' => $journalEntryId]);
    }

    public function forAccount(int $accountId): static
    {
        return $this->state(['account_id' => $accountId]);
    }

    public function debit(int $amountMinor, ?string $currency = null): static
    {
        return $this->state([
            'debit_minor' => $amountMinor,
            'credit_minor' => 0,
            'currency' => $currency,
        ]);
    }

    public function credit(int $amountMinor, ?string $currency = null): static
    {
        return $this->state([
            'debit_minor' => 0,
            'credit_minor' => $amountMinor,
            'currency' => $currency,
        ]);
    }

    public function balancedLine(int $amountMinor): static
    {
        return $this->state([
            'debit_minor' => $amountMinor,
            'credit_minor' => $amountMinor,
        ]);
    }

    public function memo(string $memo): static
    {
        return $this->state(['memo' => $memo]);
    }
}
