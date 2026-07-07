<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Project;
use Headless\Accounting\Models\ProjectTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectTaskFactory extends Factory
{
    protected $model = ProjectTask::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => ucwords($this->faker->unique()->words(3, true)),
            'due_at' => now()->addDays($this->faker->numberBetween(1, 30))->toDateString(),
            'billable' => true,
            'estimated_minutes' => 60,
        ];
    }

    public function forProject(int $projectId): static
    {
        return $this->state(['project_id' => $projectId]);
    }

    public function billable(): static
    {
        return $this->state(['billable' => true]);
    }

    public function notBillable(): static
    {
        return $this->state(['billable' => false]);
    }

    public function estimated(int $minutes): static
    {
        return $this->state(['estimated_minutes' => $minutes]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(['due_at' => $date]);
    }

    public function name(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
