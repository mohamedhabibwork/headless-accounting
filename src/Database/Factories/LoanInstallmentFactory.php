<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Loan;
use Headless\Accounting\Models\LoanInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanInstallmentFactory extends Factory
{
    protected $model = LoanInstallment::class;

    public function definition(): array
    {
        $principal = $this->faker->numberBetween(10000, 200000);
        $interest = $this->faker->numberBetween(100, 5000);

        return [
            'loan_id' => Loan::factory(),
            'installment_no' => 1,
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'principal_minor' => $principal,
            'interest_minor' => $interest,
            'total_minor' => $principal + $interest,
            'paid_minor' => 0,
            'paid_at' => null,
            'state' => 'pending',
        ];
    }

    public function forLoan(int $loanId): static
    {
        return $this->state(['loan_id' => $loanId]);
    }

    public function installment(int $number): static
    {
        return $this->state(['installment_no' => $number]);
    }

    public function paid(): static
    {
        return $this->state(function (array $attrs) {
            $total = (int) $attrs['total_minor'];

            return [
                'state' => 'paid',
                'paid_minor' => $total,
                'paid_at' => now()->toDateString(),
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(['state' => 'overdue', 'due_date' => now()->subDay()->toDateString()]);
    }

    public function partial(int $paidMinor): static
    {
        return $this->state([
            'state' => 'partial',
            'paid_minor' => $paidMinor,
            'paid_at' => now()->toDateString(),
        ]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(['due_date' => $date]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
