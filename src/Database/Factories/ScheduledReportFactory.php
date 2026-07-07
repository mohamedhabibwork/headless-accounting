<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ScheduledReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledReportFactory extends Factory
{
    protected $model = ScheduledReport::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => $this->faker->unique()->words(3, true).' Report',
            'report_code' => strtoupper($this->faker->unique()->bothify('RPT_##??##')),
            'filters' => [],
            'frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'quarterly']),
            'recipients' => [['email' => $this->faker->safeEmail()]],
            'format' => $this->faker->randomElement(['pdf', 'csv', 'xlsx']),
            'active' => true,
        ];
    }

    public function report(string $reportCode, ?string $name = null): static
    {
        return $this->state([
            'report_code' => $reportCode,
            'name' => $name ?? ucwords(strtolower(str_replace('_', ' ', $reportCode))),
        ]);
    }

    public function daily(): static
    {
        return $this->state(['frequency' => 'daily']);
    }

    public function weekly(): static
    {
        return $this->state(['frequency' => 'weekly']);
    }

    public function monthly(): static
    {
        return $this->state(['frequency' => 'monthly']);
    }

    public function recipients(array $recipients): static
    {
        return $this->state(['recipients' => $recipients]);
    }

    public function format(string $format): static
    {
        return $this->state(['format' => $format]);
    }

    public function filters(array $filters): static
    {
        return $this->state(['filters' => $filters]);
    }

    public function active(): static
    {
        return $this->state(['active' => true]);
    }

    public function paused(): static
    {
        return $this->state(['active' => false]);
    }
}
