<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\InventoryTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryTransferFactory extends Factory
{
    protected $model = InventoryTransfer::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'number' => 'TR-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'from_location_id' => null,
            'to_location_id' => null,
            'transferred_at' => now()->toDateString(),
            'state' => 'draft',
            'lines' => [],
            'journal_entry_id' => null,
        ];
    }

    public function betweenLocations(int $from, int $to): static
    {
        return $this->state([
            'from_location_id' => $from,
            'to_location_id' => $to,
        ]);
    }

    public function inTransit(): static
    {
        return $this->state(['state' => 'in_transit']);
    }

    public function received(): static
    {
        return $this->state(['state' => 'received']);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => 'cancelled']);
    }

    public function withLines(array $lines): static
    {
        return $this->state(['lines' => $lines]);
    }
}
