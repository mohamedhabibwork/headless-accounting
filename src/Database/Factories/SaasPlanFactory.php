<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\SaasPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaasPlanFactory extends Factory
{
    protected $model = SaasPlan::class;

    public function definition(): array
    {
        return [
            'code' => strtolower($this->faker->unique()->slug(2)),
            'name' => ucwords($this->faker->unique()->words(2, true)),
            'price_monthly' => 99.0,
            'features' => ['accounting', 'invoicing'],
            'limits' => ['users' => 5, 'invoices_per_month' => 1000],
            'active' => true,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function starter(): static
    {
        return $this->state([
            'code' => 'starter',
            'name' => 'Starter',
            'price_monthly' => 0,
            'limits' => ['users' => 1, 'invoices_per_month' => 50],
        ]);
    }

    public function pro(): static
    {
        return $this->state([
            'code' => 'pro',
            'name' => 'Pro',
            'price_monthly' => 99,
            'features' => ['accounting', 'invoicing', 'inventory', 'warehouses'],
            'limits' => ['users' => 10, 'invoices_per_month' => 10000],
        ]);
    }

    public function enterprise(): static
    {
        return $this->state([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'price_monthly' => 999,
            'features' => ['*'],
            'limits' => ['users' => -1],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function price(float $monthly): static
    {
        return $this->state(['price_monthly' => $monthly]);
    }

    public function features(array $features): static
    {
        return $this->state(['features' => $features]);
    }

    public function limits(array $limits): static
    {
        return $this->state(['limits' => $limits]);
    }
}
