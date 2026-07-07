<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountLimitation;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountLimitationFactory extends Factory
{
    protected $model = DiscountLimitation::class;

    public function definition(): array
    {
        return [
            'discount_id' => Discount::factory(),
            'type' => $this->faker->randomElement([
                'uses_per_customer', 'uses_total', 'one_per_order',
                'combines_with', 'excludes',
            ]),
            'config' => ['limit' => 1],
            'position' => $this->faker->numberBetween(0, 5),
        ];
    }

    public function forDiscount(int $discountId): static
    {
        return $this->state(['discount_id' => $discountId]);
    }

    public function type(string $type, array $config = []): static
    {
        return $this->state([
            'type' => $type,
            'config' => $config ?: ['limit' => 1],
        ]);
    }

    public function usesPerCustomer(int $limit = 1): static
    {
        return $this->state([
            'type' => 'uses_per_customer',
            'config' => ['limit' => $limit],
        ]);
    }

    public function usesTotal(int $limit = 100): static
    {
        return $this->state([
            'type' => 'uses_total',
            'config' => ['limit' => $limit],
        ]);
    }

    public function onePerOrder(): static
    {
        return $this->state([
            'type' => 'one_per_order',
            'config' => [],
        ]);
    }

    public function position(int $position): static
    {
        return $this->state(['position' => $position]);
    }
}
