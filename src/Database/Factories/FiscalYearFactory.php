<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween((int) date('Y') - 3, (int) date('Y') + 1);

        return [
            'name' => 'FY '.$year,
            'starts_at' => sprintf('%04d-01-01', $year),
            'ends_at' => sprintf('%04d-12-31', $year),
            'closed' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(['closed' => true]);
    }

    public function forYear(int $year): static
    {
        return $this->state([
            'name' => 'FY '.$year,
            'starts_at' => sprintf('%04d-01-01', $year),
            'ends_at' => sprintf('%04d-12-31', $year),
        ]);
    }

    public function currentYear(): static
    {
        return $this->forYear((int) date('Y'));
    }
}
