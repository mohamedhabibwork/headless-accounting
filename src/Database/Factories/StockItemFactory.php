<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockItemFactory extends Factory
{
    protected $model = StockItem::class;

    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'location_id' => Location::factory(),
            'bin_id' => null,
            'on_hand' => 100,
            'reserved' => 0,
            'incoming' => 0,
            'min_stock' => 10,
            'max_stock' => 200,
            'reorder_point' => 20,
            'average_cost_minor' => 1500,
            'currency' => 'EUR',
        ];
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(['variant_id' => $variantId]);
    }

    public function atLocation(int $locationId, ?int $binId = null): static
    {
        return $this->state([
            'location_id' => $locationId,
            'bin_id' => $binId,
        ]);
    }

    public function onHand(int $units): static
    {
        return $this->state(['on_hand' => $units]);
    }

    public function reserved(int $units): static
    {
        return $this->state(['reserved' => $units]);
    }

    public function available(int $units = 50): static
    {
        return $this->state([
            'on_hand' => $units,
            'reserved' => 0,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(['on_hand' => 0, 'reserved' => 0, 'incoming' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(['on_hand' => 5]);
    }

    public function reorderNeeded(): static
    {
        return $this->state(['on_hand' => 5, 'reorder_point' => 20]);
    }

    public function withIncoming(int $units): static
    {
        return $this->state(['incoming' => $units]);
    }

    public function averageCost(int $costMinor, string $currency = 'EUR'): static
    {
        return $this->state([
            'average_cost_minor' => $costMinor,
            'currency' => $currency,
        ]);
    }
}
