<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderAdjustmentFactory extends Factory
{
    protected $model = OrderAdjustment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => null,
            'discount_id' => null,
            'type' => $this->faker->randomElement(['discount', 'surcharge', 'shipping', 'tax', 'fee']),
            'name' => ucwords($this->faker->words(2, true)),
            'amount_minor' => -100,
            'currency' => 'EUR',
        ];
    }

    public function forOrder(int $orderId): static
    {
        return $this->state(['order_id' => $orderId]);
    }

    public function discount(?int $discountId = null): static
    {
        return $this->state([
            'type' => 'discount',
            'amount_minor' => -$this->faker->numberBetween(50, 1000),
            'discount_id' => $discountId,
        ]);
    }

    public function surcharge(int $amountMinor): static
    {
        return $this->state(['type' => 'surcharge', 'amount_minor' => $amountMinor]);
    }

    public function shipping(int $amountMinor): static
    {
        return $this->state(['type' => 'shipping', 'amount_minor' => $amountMinor]);
    }

    public function tax(int $amountMinor): static
    {
        return $this->state(['type' => 'tax', 'amount_minor' => $amountMinor]);
    }

    public function fee(int $amountMinor): static
    {
        return $this->state(['type' => 'fee', 'amount_minor' => $amountMinor]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
