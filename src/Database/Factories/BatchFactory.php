<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Enums\Inventory\BatchStatus;
use Headless\Accounting\Models\Batch;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'variant_id' => ProductVariant::factory(),
            'batch_number' => strtoupper(uniqid('B-')),
            'supplier_batch_number' => null,
            'production_batch_number' => null,
            'manufacturing_date' => now()->subDays(30)->toDateString(),
            'expiration_date' => now()->addDays(180)->toDateString(),
            'status' => BatchStatus::Active,
            'attributes' => null,
            'notes' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'expiration_date' => now()->subDays(10)->toDateString(),
            'status' => BatchStatus::Expired,
        ]);
    }

    public function quarantined(): static
    {
        return $this->state(['status' => BatchStatus::Quarantined]);
    }

    public function recalled(): static
    {
        return $this->state(['status' => BatchStatus::Recalled]);
    }

    public function depleted(): static
    {
        return $this->state(['status' => BatchStatus::Depleted]);
    }

    public function nearExpiry(int $days = 7): static
    {
        return $this->state([
            'expiration_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function withSupplier(?string $supplierBatch = null): static
    {
        return $this->state([
            'supplier_batch_number' => $supplierBatch ?? 'SUP-'.strtoupper($this->faker->bothify('??####')),
        ]);
    }
}
