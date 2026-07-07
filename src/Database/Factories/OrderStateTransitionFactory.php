<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderStateTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderStateTransitionFactory extends Factory
{
    protected $model = OrderStateTransition::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from' => Order::STATE_CART,
            'to' => Order::STATE_PLACED,
            'reason' => $this->faker->optional(0.5)->sentence(),
            'actor_type' => null,
            'actor_id' => null,
        ];
    }

    public function forOrder(int $orderId): static
    {
        return $this->state(['order_id' => $orderId]);
    }

    public function transition(string $from, string $to, ?string $reason = null): static
    {
        return $this->state([
            'from' => $from,
            'to' => $to,
            'reason' => $reason,
        ]);
    }

    public function byActor(string $type, int $id): static
    {
        return $this->state([
            'actor_type' => $type,
            'actor_id' => $id,
        ]);
    }

    public function placed(): static
    {
        return $this->transition(Order::STATE_CART, Order::STATE_PLACED);
    }

    public function paid(): static
    {
        return $this->transition(Order::STATE_PLACED, Order::STATE_PAID);
    }

    public function fulfilled(): static
    {
        return $this->transition(Order::STATE_PAID, Order::STATE_FULFILLED);
    }

    public function cancelled(): static
    {
        return $this->transition(Order::STATE_PLACED, Order::STATE_CANCELLED);
    }
}
