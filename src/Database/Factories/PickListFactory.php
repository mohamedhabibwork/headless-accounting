<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\PickList;
use Headless\Accounting\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class PickListFactory extends Factory
{
    protected $model = PickList::class;

    public function definition(): array
    {
        return [
            'fulfillment_plan_id' => FulfillmentPlan::factory(),
            'warehouse_id' => Warehouse::factory(),
            'number' => 'PL-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'state' => PickList::STATE_OPEN,
            'picker_name' => $this->faker->optional(0.5)->name(),
            'routes' => null,
            'assigned_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function forFulfillmentPlan(int $planId): static
    {
        return $this->state(['fulfillment_plan_id' => $planId]);
    }

    public function forWarehouse(int $warehouseId): static
    {
        return $this->state(['warehouse_id' => $warehouseId]);
    }

    public function open(): static
    {
        return $this->state(['state' => PickList::STATE_OPEN]);
    }

    public function assigned(?string $pickerName = null): static
    {
        return $this->state([
            'state' => PickList::STATE_ASSIGNED,
            'picker_name' => $pickerName ?? $this->faker->name(),
            'assigned_at' => now(),
        ]);
    }

    public function picking(?string $pickerName = null): static
    {
        return $this->state([
            'state' => PickList::STATE_PICKING,
            'picker_name' => $pickerName ?? $this->faker->name(),
            'started_at' => now(),
        ]);
    }

    public function picked(): static
    {
        return $this->state([
            'state' => PickList::STATE_PICKED,
            'completed_at' => now(),
        ]);
    }

    public function packed(): static
    {
        return $this->state(['state' => PickList::STATE_PACKED]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => PickList::STATE_CANCELLED]);
    }

    public function withRoutes(array $routes): static
    {
        return $this->state(['routes' => $routes]);
    }
}
