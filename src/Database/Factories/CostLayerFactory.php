<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\CostLayer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CostLayerFactory extends Factory
{
    protected $model = CostLayer::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'variant_id' => null,
            'location_id' => null,
            'received_at' => now()->subDays($this->faker->numberBetween(1, 180))->toDateString(),
            'manufacturing_date' => null,
            'expiration_date' => null,
            'batch_number' => null,
            'quantity_received' => 100,
            'quantity_remaining' => 80,
            'unit_cost_minor' => 1500,
            'currency' => 'EUR',
            'source' => 'gr',
            'source_document_type' => null,
            'source_document_id' => null,
        ];
    }

    public function fullyRemaining(): static
    {
        return $this->state(fn (array $attrs) => [
            'quantity_remaining' => $attrs['quantity_received'],
        ]);
    }

    public function depleted(): static
    {
        return $this->state(['quantity_remaining' => 0]);
    }

    public function withBatch(string $batchNumber): static
    {
        return $this->state(['batch_number' => $batchNumber]);
    }

    public function expiringSoon(int $days = 7): static
    {
        return $this->state(['expiration_date' => now()->addDays($days)->toDateString()]);
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(['variant_id' => $variantId]);
    }

    public function atLocation(int $locationId): static
    {
        return $this->state(['location_id' => $locationId]);
    }

    public function source(string $type, int $id): static
    {
        return $this->state([
            'source' => $type,
            'source_document_type' => $type,
            'source_document_id' => $id,
        ]);
    }
}
