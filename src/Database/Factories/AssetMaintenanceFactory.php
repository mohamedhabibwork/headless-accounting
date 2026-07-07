<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\AssetMaintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetMaintenanceFactory extends Factory
{
    protected $model = AssetMaintenance::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'service_at' => now()->subDays($this->faker->numberBetween(1, 365))->toDateString(),
            'action' => $this->faker->randomElement(['inspection', 'repair', 'overhaul', 'cleaning', 'replacement']),
            'cost_minor' => $this->faker->numberBetween(1000, 100000),
            'currency' => 'EUR',
            'vendor_id' => null,
            'notes' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function forAsset(int $assetId): static
    {
        return $this->state(['asset_id' => $assetId]);
    }

    public function action(string $action): static
    {
        return $this->state(['action' => $action]);
    }

    public function repair(int $costMinor = 5000): static
    {
        return $this->state(['action' => 'repair', 'cost_minor' => $costMinor]);
    }

    public function inspection(): static
    {
        return $this->state(['action' => 'inspection', 'cost_minor' => 0]);
    }

    public function servicedOn(string $date): static
    {
        return $this->state(['service_at' => $date]);
    }
}
