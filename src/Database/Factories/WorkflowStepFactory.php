<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\WorkflowDefinition;
use Headless\Accounting\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowStepFactory extends Factory
{
    protected $model = WorkflowStep::class;

    public function definition(): array
    {
        return [
            'definition_id' => WorkflowDefinition::factory(),
            'order' => $this->faker->numberBetween(1, 5),
            'name' => ucwords($this->faker->unique()->words(2, true)),
            'approver_type' => $this->faker->randomElement(['user', 'role', 'manager', 'amount_threshold']),
            'approver_config' => ['role' => 'manager'],
            'min_amount_minor' => null,
            'max_amount_minor' => null,
            'mode' => $this->faker->randomElement(['any', 'all', 'majority']),
            'required' => true,
        ];
    }

    public function forDefinition(int $definitionId): static
    {
        return $this->state(['definition_id' => $definitionId]);
    }

    public function order(int $order): static
    {
        return $this->state(['order' => $order]);
    }

    public function approverRole(string $role): static
    {
        return $this->state([
            'approver_type' => 'role',
            'approver_config' => ['role' => $role],
        ]);
    }

    public function approverManager(): static
    {
        return $this->state([
            'approver_type' => 'manager',
            'approver_config' => ['level' => 1],
        ]);
    }

    public function amountThreshold(?int $minMinor = null, ?int $maxMinor = null): static
    {
        return $this->state([
            'approver_type' => 'amount_threshold',
            'approver_config' => [
                'min_amount_minor' => $minMinor,
                'max_amount_minor' => $maxMinor,
            ],
        ]);
    }

    public function optional(): static
    {
        return $this->state(['required' => false]);
    }

    public function required(): static
    {
        return $this->state(['required' => true]);
    }

    public function mode(string $mode): static
    {
        return $this->state(['mode' => $mode]);
    }

    public function anyOf(): static
    {
        return $this->state(['mode' => 'any']);
    }

    public function allOf(): static
    {
        return $this->state(['mode' => 'all']);
    }
}
