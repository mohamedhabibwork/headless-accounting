<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ExpenseClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseClaimFactory extends Factory
{
    protected $model = ExpenseClaim::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'employee_id' => null,
            'department_id' => null,
            'project_id' => null,
            'number' => 'EXP-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'expense_date' => now()->subDays($this->faker->numberBetween(1, 60))->toDateString(),
            'state' => 'draft',
            'currency' => 'EUR',
            'total_minor' => 0,
            'approval_id' => null,
            'description' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['state' => 'draft']);
    }

    public function submitted(): static
    {
        return $this->state(['state' => 'submitted']);
    }

    public function approved(): static
    {
        return $this->state(['state' => 'approved']);
    }

    public function reimbursed(): static
    {
        return $this->state(['state' => 'reimbursed']);
    }

    public function rejected(): static
    {
        return $this->state(['state' => 'rejected']);
    }

    public function forEmployee(int $employeeId): static
    {
        return $this->state(['employee_id' => $employeeId]);
    }

    public function forDepartment(int $departmentId): static
    {
        return $this->state(['department_id' => $departmentId]);
    }

    public function forProject(int $projectId): static
    {
        return $this->state(['project_id' => $projectId]);
    }

    public function amount(int $amountMinor, ?string $currency = null): static
    {
        return $this->state([
            'total_minor' => $amountMinor,
            'currency' => $currency,
        ]);
    }

    public function description(string $description): static
    {
        return $this->state(['description' => $description]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
