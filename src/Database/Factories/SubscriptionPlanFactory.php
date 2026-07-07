<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => ucwords($this->faker->unique()->words(2, true)).' Plan',
            'description' => $this->faker->optional(0.5)->sentence(),
            'currency' => 'EUR',
            'price_minor' => 1999,
            'interval' => 'month',
            'interval_count' => 1,
            'trial_days' => 0,
            'active' => true,
        ];
    }

    public function monthly(int $priceMinor = 1999): static
    {
        return $this->state([
            'name' => 'Monthly Plan',
            'interval' => 'month',
            'interval_count' => 1,
            'price_minor' => $priceMinor,
        ]);
    }

    public function yearly(int $priceMinor = 19990): static
    {
        return $this->state([
            'name' => 'Yearly Plan',
            'interval' => 'year',
            'interval_count' => 1,
            'price_minor' => $priceMinor,
        ]);
    }

    public function weekly(int $priceMinor = 499): static
    {
        return $this->state([
            'name' => 'Weekly Plan',
            'interval' => 'week',
            'interval_count' => 1,
            'price_minor' => $priceMinor,
        ]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(['trial_days' => $days]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function price(int $priceMinor, ?string $currency = null): static
    {
        return $this->state([
            'price_minor' => $priceMinor,
            'currency' => $currency,
        ]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
