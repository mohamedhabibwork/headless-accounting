<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ApprovalInstance;
use Headless\Accounting\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalInstanceFactory extends Factory
{
    protected $model = ApprovalInstance::class;

    public function definition(): array
    {
        return [
            'definition_id' => WorkflowDefinition::factory(),
            'company_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'state' => $this->faker->randomElement(['pending', 'in_progress', 'approved', 'rejected', 'cancelled']),
            'current_step' => 1,
        ];
    }

    public function forDefinition(int $definitionId): static
    {
        return $this->state(['definition_id' => $definitionId]);
    }

    public function forSubject(string $type, int $id): static
    {
        return $this->state([
            'subject_type' => $type,
            'subject_id' => $id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(['state' => 'pending']);
    }

    public function inProgress(): static
    {
        return $this->state(['state' => 'in_progress']);
    }

    public function approved(): static
    {
        return $this->state(['state' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(['state' => 'rejected']);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => 'cancelled']);
    }

    public function onStep(int $step): static
    {
        return $this->state(['current_step' => $step]);
    }
}
