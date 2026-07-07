<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'code' => 'PRJ-'.strtoupper($this->faker->unique()->bothify('##??##')),
            'name' => $this->faker->unique()->catchPhrase(),
            'customer_id' => null,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'budget_minor' => 1000000,
            'currency' => 'EUR',
            'progress_pct' => 0,
            'state' => 'planning',
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function planning(): static
    {
        return $this->state(['state' => 'planning']);
    }

    public function active(): static
    {
        return $this->state(['state' => 'active']);
    }

    public function onHold(): static
    {
        return $this->state(['state' => 'on_hold']);
    }

    public function completed(): static
    {
        return $this->state([
            'state' => 'completed',
            'progress_pct' => 100,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => 'cancelled']);
    }

    public function progress(float $pct): static
    {
        return $this->state(['progress_pct' => $pct]);
    }

    public function budget(int $budgetMinor, ?string $currency = null): static
    {
        return $this->state([
            'budget_minor' => $budgetMinor,
            'currency' => $currency,
        ]);
    }

    public function withDates(string $start, string $end): static
    {
        return $this->state([
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }
}
