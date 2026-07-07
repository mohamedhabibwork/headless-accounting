<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\PickList;
use Headless\Accounting\Models\PickListLine;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PickListLineFactory extends Factory
{
    protected $model = PickListLine::class;

    public function definition(): array
    {
        return [
            'pick_list_id' => PickList::factory(),
            'bin_id' => null,
            'variant_id' => ProductVariant::factory(),
            'stock_item_id' => null,
            'quantity_requested' => 5,
            'quantity_picked' => 0,
            'state' => PickListLine::STATE_PENDING,
            'note' => null,
            'pick_sequence' => $this->faker->numberBetween(0, 50),
            'picked_at' => null,
        ];
    }

    public function forPickList(int $pickListId): static
    {
        return $this->state(['pick_list_id' => $pickListId]);
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(['variant_id' => $variantId]);
    }

    public function fromBin(int $binId): static
    {
        return $this->state(['bin_id' => $binId]);
    }

    public function request(int $quantity): static
    {
        return $this->state(['quantity_requested' => $quantity]);
    }

    public function picked(int $quantity): static
    {
        return $this->state([
            'quantity_picked' => $quantity,
            'state' => PickListLine::STATE_PICKED,
            'picked_at' => now(),
        ]);
    }

    public function short(int $pickedQuantity): static
    {
        return $this->state([
            'quantity_picked' => $pickedQuantity,
            'state' => PickListLine::STATE_SHORT,
            'picked_at' => now(),
        ]);
    }

    public function skipped(): static
    {
        return $this->state([
            'state' => PickListLine::STATE_SKIPPED,
            'note' => 'Item not found',
        ]);
    }

    public function forStockItem(int $stockItemId): static
    {
        return $this->state(['stock_item_id' => $stockItemId]);
    }

    public function pickSequence(int $sequence): static
    {
        return $this->state(['pick_sequence' => $sequence]);
    }
}
