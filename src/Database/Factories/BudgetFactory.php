<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $year = (int) date('Y');

        return [
            'company_id' => null,
            'name' => 'Budget '.date('Y').' '.strtoupper($this->faker->bothify('##??')),
            'scope' => $this->faker->randomElement(['company', 'department', 'cost_center', 'project']),
            'department_id' => null,
            'cost_center_id' => null,
            'project_id' => null,
            'year' => $year,
            'currency' => 'EUR',
            'approved' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(['approved' => true]);
    }

    public function unapproved(): static
    {
        return $this->state(['approved' => false]);
    }

    public function forYear(int $year): static
    {
        return $this->state(['year' => $year]);
    }

    public function forDepartment(int $departmentId): static
    {
        return $this->state([
            'scope' => 'department',
            'department_id' => $departmentId,
        ]);
    }

    public function forCostCenter(int $costCenterId): static
    {
        return $this->state([
            'scope' => 'cost_center',
            'cost_center_id' => $costCenterId,
        ]);
    }

    public function forProject(int $projectId): static
    {
        return $this->state([
            'scope' => 'project',
            'project_id' => $projectId,
        ]);
    }

    public function name(string $name): static
    {
        return $this->state(['name' => $name]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
