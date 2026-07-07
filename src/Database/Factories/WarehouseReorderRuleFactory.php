<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseReorderRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseReorderRuleFactory extends Factory
{
    protected $model = WarehouseReorderRule::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'variant_id' => ProductVariant::factory(),
            'min_stock' => 10,
            'max_stock' => 100,
            'safety_stock' => 5,
            'reorder_point' => 20,
            'reorder_quantity' => 50,
            'lead_time_days' => 7,
            'automatic_replenishment' => false,
            'preferred_vendor_code' => null,
        ];
    }

    public function automatic(): static
    {
        return $this->state(['automatic_replenishment' => true]);
    }

    public function forWarehouse(int $warehouseId): static
    {
        return $this->state(['warehouse_id' => $warehouseId]);
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(['variant_id' => $variantId]);
    }

    public function preferredVendor(string $vendorCode): static
    {
        return $this->state(['preferred_vendor_code' => $vendorCode]);
    }

    public function threshold(int $min, int $max, int $reorderPoint): static
    {
        return $this->state([
            'min_stock' => $min,
            'max_stock' => $max,
            'reorder_point' => $reorderPoint,
        ]);
    }

    public function leadTime(int $days): static
    {
        return $this->state(['lead_time_days' => $days]);
    }

    public function safety(int $units): static
    {
        return $this->state(['safety_stock' => $units]);
    }

    public function reorderQty(int $units): static
    {
        return $this->state(['reorder_quantity' => $units]);
    }
}
