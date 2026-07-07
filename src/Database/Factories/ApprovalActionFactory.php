<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ApprovalAction;
use Headless\Accounting\Models\ApprovalInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalActionFactory extends Factory
{
    protected $model = ApprovalAction::class;

    public function definition(): array
    {
        return [
            'instance_id' => ApprovalInstance::factory(),
            'step_id' => null,
            'decision' => $this->faker->randomElement(['approved', 'rejected', 'returned']),
            'actor_type' => null,
            'actor_id' => null,
            'notes' => $this->faker->optional(0.5)->sentence(),
            'decision_at' => now(),
        ];
    }

    public function forInstance(int $instanceId): static
    {
        return $this->state(['instance_id' => $instanceId]);
    }

    public function forStep(int $stepId): static
    {
        return $this->state(['step_id' => $stepId]);
    }

    public function byActor(string $type, int $id): static
    {
        return $this->state([
            'actor_type' => $type,
            'actor_id' => $id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(['decision' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(['decision' => 'rejected']);
    }

    public function returned(): static
    {
        return $this->state(['decision' => 'returned']);
    }

    public function noted(string $notes): static
    {
        return $this->state(['notes' => $notes]);
    }
}
