<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ChangeHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChangeHistoryFactory extends Factory
{
    protected $model = ChangeHistory::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'actor_type' => null,
            'actor_id' => null,
            'before' => ['status' => 'draft'],
            'after' => ['status' => 'published'],
            'event' => $this->faker->randomElement(['updated', 'created', 'deleted', 'state_changed']),
            'reason' => $this->faker->optional(0.4)->sentence(),
        ];
    }

    public function forSubject(string $type, int $id): static
    {
        return $this->state([
            'subject_type' => $type,
            'subject_id' => $id,
        ]);
    }

    public function byActor(string $type, int $id): static
    {
        return $this->state([
            'actor_type' => $type,
            'actor_id' => $id,
        ]);
    }

    public function event(string $event): static
    {
        return $this->state(['event' => $event]);
    }

    public function created(): static
    {
        return $this->state(['event' => 'created', 'before' => null]);
    }

    public function deleted(): static
    {
        return $this->state(['event' => 'deleted', 'after' => null]);
    }

    public function stateChanged(string $from, string $to): static
    {
        return $this->state([
            'event' => 'state_changed',
            'before' => ['state' => $from],
            'after' => ['state' => $to],
        ]);
    }

    public function withChanges(array $before, array $after): static
    {
        return $this->state([
            'before' => $before,
            'after' => $after,
        ]);
    }

    public function reason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }
}
