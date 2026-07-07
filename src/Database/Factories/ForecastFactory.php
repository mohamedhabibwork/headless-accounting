<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Budget;
use Headless\Accounting\Models\Forecast;
use Illuminate\Database\Eloquent\Factories\Factory;

class ForecastFactory extends Factory
{
    protected $model = Forecast::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'account_id' => Account::factory(),
            'month' => $this->faker->numberBetween(1, 12),
            'forecast_minor' => $this->faker->numberBetween(50000, 500000),
            'confidence' => $this->faker->randomElement(['low', 'medium', 'high']),
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

    public function forecast(int $amountMinor): static
    {
        return $this->state(['forecast_minor' => $amountMinor]);
    }

    public function confidence(string $confidence): static
    {
        return $this->state(['confidence' => $confidence]);
    }

    public function highConfidence(): static
    {
        return $this->state(['confidence' => 'high']);
    }

    public function lowConfidence(): static
    {
        return $this->state(['confidence' => 'low']);
    }
}
