<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Bom;
use Headless\Accounting\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionOrderFactory extends Factory
{
    protected $model = ProductionOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'number' => 'PO-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'bom_id' => Bom::factory(),
            'quantity_to_produce' => 10,
            'scheduled_date' => now()->addDay()->toDateString(),
            'state' => 'draft',
            'journal_entry_id' => null,
        ];
    }

    public function forBom(int $bomId): static
    {
        return $this->state(['bom_id' => $bomId]);
    }

    public function quantity(int $quantity): static
    {
        return $this->state(['quantity_to_produce' => $quantity]);
    }

    public function scheduledFor(string $date): static
    {
        return $this->state(['scheduled_date' => $date]);
    }

    public function inState(string $state): static
    {
        return $this->state(['state' => $state]);
    }

    public function draft(): static
    {
        return $this->state(['state' => 'draft']);
    }

    public function released(): static
    {
        return $this->state(['state' => 'released']);
    }

    public function inProgress(): static
    {
        return $this->state(['state' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state(['state' => 'completed']);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => 'cancelled']);
    }

    public function posted(?int $journalEntryId = null): static
    {
        return $this->state([
            'state' => 'posted',
            'journal_entry_id' => $journalEntryId,
        ]);
    }
}
