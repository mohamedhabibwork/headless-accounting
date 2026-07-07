<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'plan_id' => null,
            'customer_id' => null,
            'starts_at' => now()->toDateString(),
            'trial_ends_at' => null,
            'current_period_starts_at' => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
            'cancelled_at' => null,
            'state' => 'active',
            'quantity' => 1,
            'deferred_revenue_minor' => 0,
            'currency' => 'EUR',
        ];
    }

    public function forPlan(int $planId): static
    {
        return $this->state(['plan_id' => $planId]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function active(): static
    {
        return $this->state(['state' => 'active']);
    }

    public function trial(): static
    {
        return $this->state([
            'state' => 'trial',
            'trial_ends_at' => now()->addDays(14)->toDateString(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(['state' => 'past_due']);
    }

    public function cancelled(): static
    {
        return $this->state([
            'state' => 'cancelled',
            'cancelled_at' => now()->toDateString(),
        ]);
    }

    public function quantity(int $quantity): static
    {
        return $this->state(['quantity' => $quantity]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
