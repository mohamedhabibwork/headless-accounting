<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Stocktake;
use Headless\Accounting\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StocktakeFactory extends Factory
{
    protected $model = Stocktake::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'number' => 'ST-'.now()->format('Ymd').'-'.$this->faker->unique()->numerify('#####'),
            'state' => Stocktake::STATE_DRAFT,
            'scope' => Stocktake::SCOPE_FULL,
            'scheduled_at' => now()->toDateString(),
            'counted_at' => null,
            'approved_at' => null,
            'posted_at' => null,
            'zones' => null,
            'variants' => null,
            'counters' => null,
            'notes' => null,
            'approved_by' => null,
            'posted_journal_entry_id' => null,
        ];
    }

    public function inState(string $state): static
    {
        return $this->state(['state' => $state]);
    }

    public function counting(): static
    {
        return $this->state(['state' => Stocktake::STATE_COUNTING]);
    }

    public function counted(): static
    {
        return $this->state([
            'state' => Stocktake::STATE_COUNTED,
            'counted_at' => now()->toDateString(),
        ]);
    }

    public function underReview(): static
    {
        return $this->state(['state' => Stocktake::STATE_UNDER_REVIEW]);
    }

    public function approved(?int $approvedByEmployeeId = null): static
    {
        return $this->state([
            'state' => Stocktake::STATE_APPROVED,
            'approved_at' => now()->toDateString(),
            'approved_by' => $approvedByEmployeeId,
        ]);
    }

    public function posted(?int $journalEntryId = null): static
    {
        return $this->state([
            'state' => Stocktake::STATE_POSTED,
            'posted_at' => now()->toDateString(),
            'posted_journal_entry_id' => $journalEntryId,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => Stocktake::STATE_CANCELLED]);
    }

    public function fullScope(): static
    {
        return $this->state(['scope' => Stocktake::SCOPE_FULL]);
    }

    public function cycleScope(): static
    {
        return $this->state(['scope' => Stocktake::SCOPE_CYCLE]);
    }

    public function zoneScope(array $zoneIds): static
    {
        return $this->state([
            'scope' => Stocktake::SCOPE_ZONE,
            'zones' => $zoneIds,
        ]);
    }

    public function variantScope(array $variantIds): static
    {
        return $this->state([
            'scope' => Stocktake::SCOPE_VARIANT,
            'variants' => $variantIds,
        ]);
    }

    public function scheduledOn(string $date): static
    {
        return $this->state(['scheduled_at' => $date]);
    }

    public function withCounters(array $counters): static
    {
        return $this->state(['counters' => $counters]);
    }
}
