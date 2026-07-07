<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class FulfillmentPlanFactory extends Factory
{
    protected $model = FulfillmentPlan::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'order_id' => Order::factory(),
            'number' => 'FP-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'strategy' => FulfillmentPlan::STRATEGY_CHEAPEST,
            'state' => FulfillmentPlan::STATE_PLANNED,
            'allocations' => [],
            'shipping_options' => [],
            'metadata' => null,
            'planned_at' => now(),
            'allocated_at' => null,
            'completed_at' => null,
        ];
    }

    public function forOrder(int $orderId): static
    {
        return $this->state(['order_id' => $orderId]);
    }

    public function strategy(string $strategy): static
    {
        return $this->state(['strategy' => $strategy]);
    }

    public function cheapest(): static
    {
        return $this->state(['strategy' => FulfillmentPlan::STRATEGY_CHEAPEST]);
    }

    public function fastest(): static
    {
        return $this->state(['strategy' => FulfillmentPlan::STRATEGY_FASTEST]);
    }

    public function closest(): static
    {
        return $this->state(['strategy' => FulfillmentPlan::STRATEGY_CLOSEST]);
    }

    public function priority(): static
    {
        return $this->state(['strategy' => FulfillmentPlan::STRATEGY_PRIORITY]);
    }

    public function manual(): static
    {
        return $this->state(['strategy' => FulfillmentPlan::STRATEGY_MANUAL]);
    }

    public function planned(): static
    {
        return $this->state(['state' => FulfillmentPlan::STATE_PLANNED]);
    }

    public function allocating(): static
    {
        return $this->state(['state' => FulfillmentPlan::STATE_ALLOCATING]);
    }

    public function allocated(): static
    {
        return $this->state([
            'state' => FulfillmentPlan::STATE_ALLOCATED,
            'allocated_at' => now(),
        ]);
    }

    public function picking(): static
    {
        return $this->state(['state' => FulfillmentPlan::STATE_PICKING]);
    }

    public function packed(): static
    {
        return $this->state(['state' => FulfillmentPlan::STATE_PACKED]);
    }

    public function shipped(): static
    {
        return $this->state(['state' => FulfillmentPlan::STATE_SHIPPED]);
    }

    public function delivered(): static
    {
        return $this->state([
            'state' => FulfillmentPlan::STATE_DELIVERED,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => FulfillmentPlan::STATE_CANCELLED]);
    }

    public function partial(): static
    {
        return $this->state(['state' => FulfillmentPlan::STATE_PARTIAL]);
    }

    public function inState(string $state): static
    {
        return $this->state(['state' => $state]);
    }

    public function withAllocations(array $allocations): static
    {
        return $this->state(['allocations' => $allocations]);
    }

    public function withShippingOptions(array $options): static
    {
        return $this->state(['shipping_options' => $options]);
    }
}
