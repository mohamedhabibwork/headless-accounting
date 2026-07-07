<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'number' => 'JE-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'source_type' => null,
            'source_id' => null,
            'period_id' => null,
            'currency' => 'EUR',
            'posted_at' => now()->toDateString(),
            'description' => $this->faker->optional(0.5)->sentence(),
            'auto_posted' => false,
        ];
    }

    public function forPeriod(int $periodId): static
    {
        return $this->state(['period_id' => $periodId]);
    }

    public function forSource(string $type, int $id): static
    {
        return $this->state([
            'source_type' => $type,
            'source_id' => $id,
        ]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function autoPosted(): static
    {
        return $this->state(['auto_posted' => true]);
    }

    public function postedOn(string $date): static
    {
        return $this->state(['posted_at' => $date]);
    }
}
