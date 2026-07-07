<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountConditionFactory extends Factory
{
    protected $model = DiscountCondition::class;

    public function definition(): array
    {
        return [
            'discount_id' => Discount::factory(),
            'type' => $this->faker->randomElement([
                'min_cart_amount', 'min_quantity', 'customer_group',
                'product_in', 'category_in', 'first_order',
            ]),
            'config' => ['amount_minor' => 5000],
            'position' => $this->faker->numberBetween(0, 5),
        ];
    }

    public function forDiscount(int $discountId): static
    {
        return $this->state(['discount_id' => $discountId]);
    }

    public function type(string $type, array $config = ['amount_minor' => 1000]): static
    {
        return $this->state([
            'type' => $type,
            'config' => $config,
        ]);
    }

    public function minCartAmount(int $amountMinor): static
    {
        return $this->state(['type' => 'min_cart_amount', 'config' => ['amount_minor' => $amountMinor]]);
    }

    public function minQuantity(int $qty): static
    {
        return $this->state(['type' => 'min_quantity', 'config' => ['quantity' => $qty]]);
    }

    public function firstOrder(): static
    {
        return $this->state(['type' => 'first_order', 'config' => []]);
    }

    public function customerGroup(string $group): static
    {
        return $this->state(['type' => 'customer_group', 'config' => ['group' => $group]]);
    }

    public function position(int $position): static
    {
        return $this->state(['position' => $position]);
    }
}
