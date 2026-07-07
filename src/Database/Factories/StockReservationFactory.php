<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockReservationFactory extends Factory
{
    protected $model = StockReservation::class;

    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'source_type' => null,
            'source_id' => null,
            'quantity' => 1,
            'expires_at' => now()->addMinutes($this->faker->numberBetween(15, 60)),
            'batch_number' => null,
            'serial_number' => null,
            'expiration_date' => null,
            'priority' => 100,
        ];
    }

    public function forStockItem(int $stockItemId): static
    {
        return $this->state(['stock_item_id' => $stockItemId]);
    }

    public function quantity(int $qty): static
    {
        return $this->state(['quantity' => $qty]);
    }

    public function priority(int $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function highPriority(): static
    {
        return $this->state(['priority' => 10]);
    }

    public function lowPriority(): static
    {
        return $this->state(['priority' => 500]);
    }

    public function expiresIn(int $minutes): static
    {
        return $this->state(['expires_at' => now()->addMinutes($minutes)]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinutes(5)]);
    }

    public function neverExpires(): static
    {
        return $this->state(['expires_at' => null]);
    }

    public function withBatch(string $batchNumber): static
    {
        return $this->state(['batch_number' => $batchNumber]);
    }

    public function withSerial(string $serial): static
    {
        return $this->state(['serial_number' => $serial]);
    }

    public function withExpiration(string $expirationDate): static
    {
        return $this->state(['expiration_date' => $expirationDate]);
    }

    public function forSource(string $type, int $id): static
    {
        return $this->state([
            'source_type' => $type,
            'source_id' => $id,
        ]);
    }
}
