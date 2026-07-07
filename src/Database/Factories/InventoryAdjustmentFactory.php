<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\InventoryAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryAdjustmentFactory extends Factory
{
    protected $model = InventoryAdjustment::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'number' => 'ADJ-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'location_id' => null,
            'adjusted_at' => now()->toDateString(),
            'reason' => $this->faker->randomElement(['stocktake', 'damage', 'correction', 'recount', 'found']),
            'lines' => [],
            'notes' => null,
            'journal_entry_id' => null,
        ];
    }

    public function stocktake(?int $stocktakeNumber = null): static
    {
        return $this->state(fn () => [
            'reason' => 'stocktake:'.($stocktakeNumber ?? 'ST-'.date('Ymd').'-'.$this->faker->numerify('#####')),
        ]);
    }

    public function forLocation(int $locationId): static
    {
        return $this->state(['location_id' => $locationId]);
    }

    public function withLines(array $lines): static
    {
        return $this->state(['lines' => $lines]);
    }
}
