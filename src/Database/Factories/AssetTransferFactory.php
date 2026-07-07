<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\AssetTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetTransferFactory extends Factory
{
    protected $model = AssetTransfer::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'from_location_id' => null,
            'to_location_id' => null,
            'from_custodian_id' => null,
            'to_custodian_id' => null,
            'transferred_at' => now()->toDateString(),
            'reason' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function forAsset(int $assetId): static
    {
        return $this->state(['asset_id' => $assetId]);
    }

    public function betweenLocations(int $from, int $to): static
    {
        return $this->state([
            'from_location_id' => $from,
            'to_location_id' => $to,
        ]);
    }

    public function fromCustodian(int $fromId): static
    {
        return $this->state(['from_custodian_id' => $fromId]);
    }

    public function toCustodian(int $toId): static
    {
        return $this->state(['to_custodian_id' => $toId]);
    }

    public function transferredOn(string $date): static
    {
        return $this->state(['transferred_at' => $date]);
    }

    public function reason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }
}
