<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Enums\Inventory\SerialNumberStatus;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\SerialNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class SerialNumberFactory extends Factory
{
    protected $model = SerialNumber::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'variant_id' => ProductVariant::factory(),
            'batch_id' => null,
            'serial' => strtoupper($this->faker->unique()->bothify('SN-############')),
            'status' => SerialNumberStatus::InStock,
            'location_id' => null,
            'bin_id' => null,
            'manufacturing_date' => now()->subDays(15)->toDateString(),
            'warranty_expires_at' => now()->addYear()->toDateString(),
            'sold_at' => null,
            'assigned_to_customer_id' => null,
            'warranty_terms' => null,
            'attributes' => null,
        ];
    }

    public function sold(): static
    {
        return $this->state([
            'status' => SerialNumberStatus::Sold,
            'sold_at' => now()->toDateString(),
        ]);
    }

    public function reserved(): static
    {
        return $this->state(['status' => SerialNumberStatus::Reserved]);
    }

    public function inTransit(): static
    {
        return $this->state(['status' => SerialNumberStatus::InTransit]);
    }

    public function returned(): static
    {
        return $this->state(['status' => SerialNumberStatus::Returned]);
    }

    public function underRepair(): static
    {
        return $this->state(['status' => SerialNumberStatus::UnderRepair]);
    }

    public function retired(): static
    {
        return $this->state(['status' => SerialNumberStatus::Retired]);
    }

    public function lost(): static
    {
        return $this->state(['status' => SerialNumberStatus::Lost]);
    }

    public function inBatch(int $batchId): static
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

    public function assignedTo(int $customerId): static
    {
        return $this->state([
            'status' => SerialNumberStatus::Sold,
            'sold_at' => now()->toDateString(),
            'assigned_to_customer_id' => $customerId,
        ]);
    }

    public function warrantyExpires(string $date): static
    {
        return $this->state(['warranty_expires_at' => $date]);
    }

    public function warrantyExpired(): static
    {
        return $this->state(['warranty_expires_at' => now()->subDay()->toDateString()]);
    }
}
