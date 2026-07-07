<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\RecurringRule;
use Headless\Accounting\Models\RecurringRuleRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringRuleRunFactory extends Factory
{
    protected $model = RecurringRuleRun::class;

    public function definition(): array
    {
        return [
            'rule_id' => RecurringRule::factory(),
            'run_at' => now()->subMinutes($this->faker->numberBetween(1, 1440)),
            'status' => $this->faker->randomElement(['success', 'failed', 'skipped']),
            'reference_id' => null,
            'error' => null,
        ];
    }

    public function forRule(int $ruleId): static
    {
        return $this->state(['rule_id' => $ruleId]);
    }

    public function succeeded(?int $referenceId = null): static
    {
        return $this->state([
            'status' => 'success',
            'reference_id' => $referenceId,
            'error' => null,
        ]);
    }

    public function failed(string $error): static
    {
        return $this->state([
            'status' => 'failed',
            'error' => $error,
        ]);
    }

    public function skipped(): static
    {
        return $this->state(['status' => 'skipped']);
    }

    public function runAt(string $when): static
    {
        return $this->state(['run_at' => $when]);
    }
}
