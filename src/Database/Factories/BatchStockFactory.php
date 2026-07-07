<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Batch;
use Headless\Accounting\Models\BatchStock;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchStockFactory extends Factory
{
    protected $model = BatchStock::class;

    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'location_id' => null,
            'bin_id' => null,
            'quantity' => 100,
            'reserved' => 0,
            'currency' => 'EUR',
            'unit_cost_minor' => 1500,
        ];
    }

    public function forBatch(int $batchId): static
    {
        return $this->state(['batch_id' => $batchId]);
    }

    public function atLocation(int $locationId, ?int $binId = null): static
    {
        return $this->state([
            'location_id' => $locationId,
            'bin_id' => $binId,
        ]);
    }

    public function reserved(int $units): static
    {
        return $this->state(['reserved' => $units]);
    }

    public function outOfStock(): static
    {
        return $this->state(['quantity' => 0, 'reserved' => 0]);
    }
}
