<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Budget;
use Headless\Accounting\Models\BudgetLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'account_id' => Account::factory(),
            'currency' => 'EUR',
            'month' => $this->faker->numberBetween(1, 12),
            'planned_minor' => 100000,
            'revised_minor' => 0,
            'actual_minor' => 0,
            'variance_pct' => 0,
        ];
    }

    public function forBudget(int $budgetId): static
    {
        return $this->state(['budget_id' => $budgetId]);
    }

    public function forAccount(int $accountId): static
    {
        return $this->state(['account_id' => $accountId]);
    }

    public function month(int $month): static
    {
        return $this->state(['month' => $month]);
    }

    public function planned(int $amountMinor): static
    {
        return $this->state(['planned_minor' => $amountMinor]);
    }

    public function revised(int $amountMinor): static
    {
        return $this->state(['revised_minor' => $amountMinor]);
    }

    public function actual(int $amountMinor): static
    {
        return $this->state(['actual_minor' => $amountMinor]);
    }

    public function variancePct(float $percent): static
    {
        return $this->state(['variance_pct' => $percent]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
