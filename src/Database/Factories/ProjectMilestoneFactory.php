<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Project;
use Headless\Accounting\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => ucwords($this->faker->unique()->words(2, true)),
            'due_at' => now()->addDays($this->faker->numberBetween(7, 90))->toDateString(),
            'achieved_at' => null,
            'revenue_minor' => 0,
            'currency' => 'EUR',
            'invoiced' => false,
            'invoice_id' => null,
        ];
    }

    public function forProject(int $projectId): static
    {
        return $this->state(['project_id' => $projectId]);
    }

    public function achieved(): static
    {
        return $this->state([
            'achieved_at' => now()->toDateString(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(['due_at' => now()->subDays($this->faker->numberBetween(1, 30))->toDateString()]);
    }

    public function revenue(int $amountMinor): static
    {
        return $this->state(['revenue_minor' => $amountMinor]);
    }

    public function invoicedAt(int $invoiceId): static
    {
        return $this->state([
            'invoiced' => true,
            'invoice_id' => $invoiceId,
        ]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(['due_at' => $date]);
    }
}
