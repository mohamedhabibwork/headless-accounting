<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'reason' => $this->faker->randomElement([
                'receive', 'pick', 'ship', 'transfer', 'adjust',
                'damage', 'stocktake', 'return', 'production',
            ]),
            'quantity' => $this->faker->numberBetween(-50, 50),
            'balance_after' => $this->faker->numberBetween(0, 200),
            'source_type' => null,
            'source_id' => null,
            'occurred_at' => now()->subMinutes($this->faker->numberBetween(0, 1440)),
        ];
    }

    public function forStockItem(int $stockItemId): static
    {
        return $this->state(['stock_item_id' => $stockItemId]);
    }

    public function receive(int $quantity, ?string $sourceType = null, ?int $sourceId = null): static
    {
        return $this->state([
            'reason' => 'receive',
            'quantity' => abs($quantity),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    public function pick(int $quantity): static
    {
        return $this->state([
            'reason' => 'pick',
            'quantity' => -abs($quantity),
        ]);
    }

    public function ship(int $quantity): static
    {
        return $this->state([
            'reason' => 'ship',
            'quantity' => -abs($quantity),
        ]);
    }

    public function transfer(int $quantity, ?int $fromStockItemId = null): static
    {
        return $this->state([
            'reason' => 'transfer',
            'quantity' => -abs($quantity),
            'source_type' => $fromStockItemId ? StockItem::class : null,
            'source_id' => $fromStockItemId,
        ]);
    }

    public function stocktake(int $quantity): static
    {
        return $this->state([
            'reason' => 'stocktake',
            'quantity' => $quantity,
        ]);
    }

    public function damage(int $quantity): static
    {
        return $this->state([
            'reason' => 'damage',
            'quantity' => -abs($quantity),
        ]);
    }

    public function reason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }
}
