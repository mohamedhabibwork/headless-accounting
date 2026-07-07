<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\AssetRevaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetRevaluationFactory extends Factory
{
    protected $model = AssetRevaluation::class;

    public function definition(): array
    {
        $previous = $this->faker->numberBetween(1000000, 10000000);

        return [
            'asset_id' => Asset::factory(),
            'revalued_at' => now()->toDateString(),
            'previous_cost_minor' => $previous,
            'new_cost_minor' => $previous + 100000,
            'revaluation_reserve_delta_minor' => 100000,
            'journal_entry_id' => null,
            'reason' => 'Annual market revaluation',
        ];
    }

    public function forAsset(int $assetId): static
    {
        return $this->state(['asset_id' => $assetId]);
    }

    public function revaluedTo(int $newCostMinor): static
    {
        return $this->state(function (array $attrs) use ($newCostMinor) {
            $previous = (int) $attrs['previous_cost_minor'];

            return [
                'new_cost_minor' => $newCostMinor,
                'revaluation_reserve_delta_minor' => $newCostMinor - $previous,
            ];
        });
    }

    public function revaluedOn(string $date): static
    {
        return $this->state(['revalued_at' => $date]);
    }

    public function reason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }

    public function posted(int $journalEntryId): static
    {
        return $this->state(['journal_entry_id' => $journalEntryId]);
    }
}
