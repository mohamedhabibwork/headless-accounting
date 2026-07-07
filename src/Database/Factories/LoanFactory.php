<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $term = $this->faker->numberBetween(6, 60);

        return [
            'company_id' => null,
            'employee_id' => null,
            'vendor_id' => null,
            'name' => 'Loan '.strtoupper($this->faker->bothify('##??##')),
            'kind' => $this->faker->randomElement(['employee_advance', 'business_loan', 'lease']),
            'currency' => 'EUR',
            'principal_minor' => $this->faker->numberBetween(100000, 10000000),
            'interest_rate_pct' => $this->faker->randomFloat(2, 1, 10),
            'term_months' => $term,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths($term)->toDateString(),
            'state' => 'active',
        ];
    }

    public function employeeAdvance(int $employeeId): static
    {
        return $this->state([
            'kind' => 'employee_advance',
            'employee_id' => $employeeId,
            'name' => 'Employee Advance',
        ]);
    }

    public function businessLoan(int $vendorId): static
    {
        return $this->state([
            'kind' => 'business_loan',
            'vendor_id' => $vendorId,
            'name' => 'Business Loan',
        ]);
    }

    public function lease(int $vendorId): static
    {
        return $this->state([
            'kind' => 'lease',
            'vendor_id' => $vendorId,
            'name' => 'Lease',
        ]);
    }

    public function principal(int $amountMinor): static
    {
        return $this->state(['principal_minor' => $amountMinor]);
    }

    public function rate(float $rate): static
    {
        return $this->state(['interest_rate_pct' => $rate]);
    }

    public function term(int $months): static
    {
        return $this->state([
            'term_months' => $months,
            'end_date' => now()->addMonths($months)->toDateString(),
        ]);
    }

    public function active(): static
    {
        return $this->state(['state' => 'active']);
    }

    public function paidOff(): static
    {
        return $this->state(['state' => 'paid_off']);
    }

    public function defaulted(): static
    {
        return $this->state(['state' => 'defaulted']);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
