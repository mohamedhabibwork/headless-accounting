<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\CurrencyRevaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyRevaluationFactory extends Factory
{
    protected $model = CurrencyRevaluation::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'currency' => $this->faker->randomElement(['USD', 'GBP', 'JPY']),
            'as_of' => now()->toDateString(),
            'breakdown' => [
                ['currency' => 'USD', 'amount_minor' => 1000, 'fx_rate' => 0.92, 'rate_delta' => -0.02],
            ],
            'journal_entry_id' => null,
        ];
    }

    public function forCompany(int $companyId): static
    {
        return $this->state(['company_id' => $companyId]);
    }

    public function forCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function asOf(string $date): static
    {
        return $this->state(['as_of' => $date]);
    }

    public function withBreakdown(array $breakdown): static
    {
        return $this->state(['breakdown' => $breakdown]);
    }

    public function postedTo(int $journalEntryId): static
    {
        return $this->state(['journal_entry_id' => $journalEntryId]);
    }
}
