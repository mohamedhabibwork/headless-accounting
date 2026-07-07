<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Project;
use Headless\Accounting\Models\ProjectWip;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectWipFactory extends Factory
{
    protected $model = ProjectWip::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'project_id' => Project::factory(),
            'as_of' => now()->toDateString(),
            'currency' => 'EUR',
            'costs_minor' => $this->faker->numberBetween(100000, 10000000),
            'recognized_revenue_minor' => $this->faker->numberBetween(50000, 5000000),
            'over_under_minor' => 0,
        ];
    }

    public function forProject(int $projectId): static
    {
        return $this->state(['project_id' => $projectId]);
    }

    public function costs(int $costsMinor): static
    {
        return $this->state(['costs_minor' => $costsMinor]);
    }

    public function recognizedRevenue(int $amountMinor): static
    {
        return $this->state(['recognized_revenue_minor' => $amountMinor]);
    }

    public function overUnder(int $amountMinor): static
    {
        return $this->state(['over_under_minor' => $amountMinor]);
    }

    public function asOf(string $date): static
    {
        return $this->state(['as_of' => $date]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
