<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Project;
use Headless\Accounting\Models\ProjectTimeBill;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectTimeBillFactory extends Factory
{
    protected $model = ProjectTimeBill::class;

    public function definition(): array
    {
        $minutes = $this->faker->numberBetween(15, 480);
        $hourlyRate = 7500;

        return [
            'project_id' => Project::factory(),
            'task_id' => null,
            'employee_id' => null,
            'date' => now()->subDays($this->faker->numberBetween(0, 30))->toDateString(),
            'minutes' => $minutes,
            'hourly_rate_minor' => $hourlyRate,
            'currency' => 'EUR',
            'amount_minor' => (int) round($minutes / 60 * $hourlyRate),
            'state' => 'draft',
            'invoice_id' => null,
        ];
    }

    public function forProject(int $projectId): static
    {
        return $this->state(['project_id' => $projectId]);
    }

    public function forTask(int $taskId): static
    {
        return $this->state(['task_id' => $taskId]);
    }

    public function forEmployee(int $employeeId): static
    {
        return $this->state(['employee_id' => $employeeId]);
    }

    public function minutes(int $minutes): static
    {
        return $this->state(fn (array $attrs) => [
            'minutes' => $minutes,
            'amount_minor' => (int) round($minutes / 60 * (int) $attrs['hourly_rate_minor']),
        ]);
    }

    public function hourlyRate(int $rateMinor): static
    {
        return $this->state(fn (array $attrs) => [
            'hourly_rate_minor' => $rateMinor,
            'amount_minor' => (int) round((int) $attrs['minutes'] / 60 * $rateMinor),
        ]);
    }

    public function billed(int $invoiceId): static
    {
        return $this->state([
            'state' => 'billed',
            'invoice_id' => $invoiceId,
        ]);
    }

    public function approved(): static
    {
        return $this->state(['state' => 'approved']);
    }
}
