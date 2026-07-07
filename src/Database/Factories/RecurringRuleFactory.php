<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\RecurringRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringRuleFactory extends Factory
{
    protected $model = RecurringRule::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => $this->faker->unique()->words(3, true),
            'kind' => $this->faker->randomElement(['journal_entry', 'invoice', 'payment', 'subscription']),
            'frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'quarterly', 'yearly']),
            'day_of_month' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'next_run_at' => now()->addDay()->toDateString(),
            'last_run_at' => null,
            'max_runs' => null,
            'runs_count' => 0,
            'template' => ['description' => 'Recurring payment'],
            'active' => true,
        ];
    }

    public function monthly(int $dayOfMonth = 1): static
    {
        return $this->state([
            'frequency' => 'monthly',
            'day_of_month' => $dayOfMonth,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(['frequency' => 'weekly']);
    }

    public function daily(): static
    {
        return $this->state(['frequency' => 'daily']);
    }

    public function yearly(): static
    {
        return $this->state(['frequency' => 'yearly']);
    }

    public function ofKind(string $kind): static
    {
        return $this->state(['kind' => $kind]);
    }

    public function maxRuns(int $max): static
    {
        return $this->state(['max_runs' => $max]);
    }

    public function paused(): static
    {
        return $this->state(['active' => false]);
    }

    public function active(): static
    {
        return $this->state(['active' => true]);
    }

    public function runs(int $count): static
    {
        return $this->state(['runs_count' => $count]);
    }

    public function template(array $template): static
    {
        return $this->state(['template' => $template]);
    }
}
