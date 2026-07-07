<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\StockWriteOff;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockWriteOffFactory extends Factory
{
    protected $model = StockWriteOff::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'warehouse_id' => null,
            'number' => 'WO-'.date('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'category' => StockWriteOff::CATEGORY_DAMAGED,
            'occurred_at' => now()->toDateString(),
            'state' => StockWriteOff::STATE_PENDING,
            'lines' => [],
            'notes' => null,
            'disposal_order_id' => null,
            'journal_entry_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['state' => StockWriteOff::STATE_APPROVED]);
    }

    public function disposed(): static
    {
        return $this->state(['state' => StockWriteOff::STATE_DISPOSED]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => StockWriteOff::STATE_CANCELLED]);
    }

    public function category(string $category): static
    {
        return $this->state(['category' => $category]);
    }

    public function damaged(): static
    {
        return $this->state(['category' => StockWriteOff::CATEGORY_DAMAGED]);
    }

    public function lost(): static
    {
        return $this->state(['category' => StockWriteOff::CATEGORY_LOST]);
    }

    public function expired(): static
    {
        return $this->state(['category' => StockWriteOff::CATEGORY_EXPIRED]);
    }

    public function obsolete(): static
    {
        return $this->state(['category' => StockWriteOff::CATEGORY_OBSOLETE]);
    }

    public function stolen(): static
    {
        return $this->state(['category' => StockWriteOff::CATEGORY_STOLEN]);
    }

    public function recalled(): static
    {
        return $this->state(['category' => StockWriteOff::CATEGORY_RECALLED]);
    }

    public function forWarehouse(int $warehouseId): static
    {
        return $this->state(['warehouse_id' => $warehouseId]);
    }

    public function forDisposalOrder(int $disposalOrderId): static
    {
        return $this->state(['disposal_order_id' => $disposalOrderId]);
    }
}
