<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\WorkflowDelegation;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowDelegationFactory extends Factory
{
    protected $model = WorkflowDelegation::class;

    public function definition(): array
    {
        return [
            'from_user_id' => null,
            'to_user_id' => null,
            'scope_type' => null,
            'scope_id' => null,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays($this->faker->numberBetween(1, 30))->toDateString(),
            'active' => true,
        ];
    }

    public function from(int $userId): static
    {
        return $this->state(['from_user_id' => $userId]);
    }

    public function to(int $userId): static
    {
        return $this->state(['to_user_id' => $userId]);
    }

    public function between(int $fromUserId, int $toUserId): static
    {
        return $this->state([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
        ]);
    }

    public function forScope(string $type, int $id): static
    {
        return $this->state([
            'scope_type' => $type,
            'scope_id' => $id,
        ]);
    }

    public function duringDays(int $days): static
    {
        return $this->state([
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
