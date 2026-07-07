<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\SaasPlan;
use Headless\Accounting\Models\SaasSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaasSubscriptionFactory extends Factory
{
    protected $model = SaasSubscription::class;

    public function definition(): array
    {
        return [
            'plan_id' => SaasPlan::factory(),
            'company_id' => null,
            'started_at' => now()->toDateString(),
            'renews_at' => now()->addMonth()->toDateString(),
            'trial_ends_at' => null,
            'state' => 'active',
            'modules_enabled' => ['accounting', 'invoicing'],
        ];
    }

    public function forPlan(int $planId): static
    {
        return $this->state(['plan_id' => $planId]);
    }

    public function active(): static
    {
        return $this->state(['state' => 'active']);
    }

    public function trial(int $days = 14): static
    {
        return $this->state([
            'state' => 'trial',
            'trial_ends_at' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(['state' => 'past_due']);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => 'cancelled']);
    }

    public function modules(array $modules): static
    {
        return $this->state(['modules_enabled' => $modules]);
    }

    public function renewsInDays(int $days): static
    {
        return $this->state(['renews_at' => now()->addDays($days)->toDateString()]);
    }
}
