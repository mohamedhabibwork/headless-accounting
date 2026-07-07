<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\DepreciationLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepreciationLineFactory extends Factory
{
    protected $model = DepreciationLine::class;

    public function definition(): array
    {
        $amount = $this->faker->numberBetween(1000, 100000);

        return [
            'asset_id' => Asset::factory(),
            'period' => now()->startOfMonth()->toDateString(),
            'amount_minor' => $amount,
            'currency' => 'EUR',
            'accumulated_minor' => 0,
            'book_value_minor' => 0,
            'fiscal_year' => (int) date('Y'),
            'journal_entry_id' => null,
            'posted' => false,
        ];
    }

    public function forAsset(int $assetId): static
    {
        return $this->state(['asset_id' => $assetId]);
    }

    public function period(string $date): static
    {
        return $this->state(['period' => $date]);
    }

    public function fiscalYear(int $year): static
    {
        return $this->state(['fiscal_year' => $year]);
    }

    public function amount(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function posted(?int $journalEntryId = null): static
    {
        return $this->state([
            'posted' => true,
            'journal_entry_id' => $journalEntryId,
        ]);
    }

    public function unposted(): static
    {
        return $this->state(['posted' => false]);
    }

    public function withBalance(int $accumulatedMinor, int $bookValueMinor): static
    {
        return $this->state([
            'accumulated_minor' => $accumulatedMinor,
            'book_value_minor' => $bookValueMinor,
        ]);
    }
}
