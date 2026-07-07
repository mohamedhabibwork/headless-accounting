<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowDefinitionFactory extends Factory
{
    protected $model = WorkflowDefinition::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'scope' => $this->faker->randomElement(['expense_claim', 'purchase_order', 'journal_entry', 'bill', 'invoice']),
            'name' => ucwords($this->faker->unique()->words(2, true)).' Workflow',
            'description' => $this->faker->optional(0.5)->sentence(),
            'config' => [
                'allow_skip' => false,
                'timeout_hours' => 72,
            ],
            'active' => true,
        ];
    }

    public function forScope(string $scope): static
    {
        return $this->state(['scope' => $scope]);
    }

    public function expenseClaimWorkflow(): static
    {
        return $this->state(['scope' => 'expense_claim', 'name' => 'Expense Approval']);
    }

    public function purchaseOrderWorkflow(): static
    {
        return $this->state(['scope' => 'purchase_order', 'name' => 'PO Approval']);
    }

    public function journalEntryWorkflow(): static
    {
        return $this->state(['scope' => 'journal_entry', 'name' => 'Journal Approval']);
    }

    public function name(string $name): static
    {
        return $this->state(['name' => $name]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function config(array $config): static
    {
        return $this->state(['config' => $config]);
    }
}
