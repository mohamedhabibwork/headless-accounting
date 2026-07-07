<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        $month = $this->faker->numberBetween(1, 12);
        $year = (int) date('Y');
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        return [
            'fiscal_year_id' => null,
            'code' => sprintf('%04d-%02d', $year, $month),
            'starts_at' => $start,
            'ends_at' => $end,
            'closed' => false,
        ];
    }

    public function forFiscalYear(int $fiscalYearId): static
    {
        return $this->state(['fiscal_year_id' => $fiscalYearId]);
    }

    public function closed(): static
    {
        return $this->state(['closed' => true]);
    }

    public function open(): static
    {
        return $this->state(['closed' => false]);
    }

    public function month(int $month, ?int $year = null): static
    {
        $y = $year ?? (int) date('Y');
        $start = sprintf('%04d-%02d-01', $y, $month);
        $end = date('Y-m-t', strtotime($start));

        return $this->state([
            'code' => sprintf('%04d-%02d', $y, $month),
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
    }
}
