<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\AssetDisposal;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetDisposalFactory extends Factory
{
    protected $model = AssetDisposal::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'disposed_at' => now()->toDateString(),
            'method' => $this->faker->randomElement(['scrap', 'sold', 'donated', 'returned', 'lost']),
            'proceeds_minor' => 0,
            'cost_at_disposal_minor' => 0,
            'accumulated_at_disposal_minor' => 0,
            'gain_loss_minor' => 0,
            'journal_entry_id' => null,
            'notes' => null,
        ];
    }

    public function forAsset(int $assetId): static
    {
        return $this->state(['asset_id' => $assetId]);
    }

    public function method(string $method): static
    {
        return $this->state(['method' => $method]);
    }

    public function scrap(): static
    {
        return $this->state(['method' => 'scrap', 'proceeds_minor' => 0]);
    }

    public function sold(int $proceedsMinor): static
    {
        return $this->state([
            'method' => 'sold',
            'proceeds_minor' => $proceedsMinor,
        ]);
    }

    public function proceeds(int $proceedsMinor): static
    {
        return $this->state(['proceeds_minor' => $proceedsMinor]);
    }

    public function gainLoss(int $amountMinor): static
    {
        return $this->state(['gain_loss_minor' => $amountMinor]);
    }

    public function disposedOn(string $date): static
    {
        return $this->state(['disposed_at' => $date]);
    }
}
