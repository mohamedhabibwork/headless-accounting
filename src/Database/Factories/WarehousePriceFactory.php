<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehousePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehousePriceFactory extends Factory
{
    protected $model = WarehousePrice::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'variant_id' => ProductVariant::factory(),
            'currency' => 'EUR',
            'amount_minor' => 1999,
            'min_quantity' => 1,
            'tax_inclusive' => false,
            'effective_from' => null,
            'effective_until' => null,
        ];
    }

    public function forWarehouse(int $warehouseId): static
    {
        return $this->state(['warehouse_id' => $warehouseId]);
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(['variant_id' => $variantId]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function priceMinor(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function taxInclusive(): static
    {
        return $this->state(['tax_inclusive' => true]);
    }

    public function bulkTier(float $minQuantity): static
    {
        return $this->state(['min_quantity' => $minQuantity]);
    }

    public function effectiveBetween(string $from, string $until): static
    {
        return $this->state([
            'effective_from' => $from,
            'effective_until' => $until,
        ]);
    }

    public function effectiveFrom(string $from): static
    {
        return $this->state(['effective_from' => $from]);
    }
}
